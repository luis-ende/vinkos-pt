<?php

namespace App\Console\Commands;

use App\Imports\EstadisticasImport;
use App\Models\Bitacora;
use App\Models\Error;
use App\Models\Visitante;
use App\Notifications\ImportFileNotification;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

class ImportEstadisticas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vinkos-pt:import-estadisticas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para importación de estadísticas mediante el importador de archivos CSV.';

    /**
     * Punto de entrada del proceso de importación.
     * Ver descripción detallada del proceso en el archivo README del proyecto.
     */
    public function handle()
    {
        try {
            $filesToImport = $this->getFilesToImport();
        } catch (\Exception $e) {
            Log::critical("Error al obtener archivos de importación: " . $e->getMessage());

            return 1;
        }

        $importedFiles = [];
        foreach ($filesToImport as $file) {
            $fileErrors = [];
            $numImportedRows = 0;
            try {
                DB::beginTransaction();

                $importedRows = $this->importStatisticsFile($file);
                if (count($importedRows) > 0) {
                    $this->updateVisitors($importedRows[0]);
                    DB::commit();

                    $numImportedRows = count($importedRows[0]);
                    $importedFiles[] = $file;
                }
            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                DB::rollBack();
                $this->error("Errores de validación al procesar archivo {$file}.");
                $fileErrors = array_merge($fileErrors, $this->getFailuresEntry($e->failures()));
            }
            catch(\Exception $e) {
                DB::rollBack();
                $this->error("Error inesperado al procesar archivo {$file}: " . $e->getMessage());
                $fileErrors[] = [
                    'category' => 'unexpected',
                    'error' => $e->getMessage()
                ];
            }

            $fileHasErrors = count($fileErrors) > 0;
            $importInfo = [
                'estatus' => $fileHasErrors ? 'no importado' : 'importado',
                'registros_procesados' => $numImportedRows,
                'errores' => count($fileErrors)
            ];
            $this->recordBitacoraEntry($file, $importInfo, $fileErrors);
            if ($fileHasErrors) {
                $this->sendEmailNotification("Ocurrió un error al importar el archivo: {$file}. ".
                    "Consultar la bitácora de errores para mayor información.");
            }
        }

        $numImportedFiles = count($importedFiles);
        if ($numImportedFiles > 0) {
            try {
                $this->postprocessFiles($importedFiles);
            } catch (\Exception $e) {
                Log::critical("Error al respaldar archivos de origen: " . $e->getMessage());
            }
        }

        $successInfo = "{$numImportedFiles} archivos importados con éxito.";
        $this->info($successInfo);
        $this->sendEmailNotification($successInfo);

        return 0;
    }

    protected function importStatisticsFile(string $file): array
    {
        $importer = new EstadisticasImport;

        $rows = $importer->toArray($file, 'imports',
                    \Maatwebsite\Excel\Excel::CSV);

        Excel::import($importer, $file, 'imports',
            \Maatwebsite\Excel\Excel::CSV);

        return $rows;
    }

    protected function updateVisitors(array $rows): void
    {
        $emails = array_map(function ($row) {
            return $row['email'];
        }, $rows);

        $visitors = Visitante::whereIn('email', $emails)
            ->select(['email', 'fecha_primera_visita', 'fecha_ultima_visita',
                'visitas_totales', 'visitas_anio_total', 'visitas_mes_actual'])
            ->whereIn('email', $emails)
            ->orderBy('email')
            ->get();

        foreach ($rows as $row) {
            $email = $row['email'];
            $visitor = $visitors->where('email', $email)->first();
            $fechaVisita = DateTime::createFromFormat('d/m/Y H:i', $row['fecha_envio']);

            $newVisitor = false;
            if (!$visitor) {
                $visitor = [
                    'email' => $row['email'],
                    'fecha_primera_visita' => $fechaVisita,
                    'visitas_totales' => 0,
                    'visitas_mes_actual' => 0,
                    'visitas_anio_total' => 0,
                ];
                $newVisitor = true;
            }

            $visitor['fecha_ultima_visita'] = $fechaVisita;
            $visitor['visitas_totales'] += 1;
            $visitor['visitas_mes_actual'] +=
                Carbon::instance($fechaVisita)->month === Carbon::today()->month ? 1 : 0;
            $visitor['visitas_anio_total'] +=
                Carbon::instance($fechaVisita)->year === Carbon::today()->year ? 1 : 0;

            if ($newVisitor) {
                $visitors->push($visitor);
            }
        }

        $this->saveVisitorsBulk($visitors->toArray());
    }

    protected function saveVisitorsBulk(array $visitors): void
    {
        $chunks = array_chunk($visitors, 500);
        foreach ($chunks as $chunk) {
            Visitante::upsert($chunk, 'email', [
                'fecha_ultima_visita',
                'visitas_totales',
                'visitas_mes_actual',
                'visitas_anio_total'
            ]);
        }
    }

    protected function postprocessFiles(array $importedFiles): void
    {
        if ($this->zipImportFiles($importedFiles)) {
            $this->deleteOriginalFiles($importedFiles);
        }
    }

    protected function zipImportFiles($files): bool
    {
        try {
            $zip = new ZipArchive();
            $date = now()->format('Y-m-d_H-i-s');

            $zipFilePath = Storage::disk('backups')->path("backup_{$date}.zip");
            if ($zip->open($zipFilePath, ZipArchive::CREATE)) {
                foreach ($files as $file) {
                    $originFilePath = Storage::disk('imports')->path($file);
                    $zip->addFile($originFilePath, $file);
                }
            }
            $zip->close();
        } catch (\Exception $e) {
            Log::critical("Error al generar archivo zip: {$zipFilePath} Error: " .
                $e->getMessage());
            return false;
        }

        return true;
    }

    protected function deleteOriginalFiles(array $files): void
    {
        Storage::disk('imports')->delete($files);
    }

    protected function recordBitacoraEntry(string $importFile, array $importInfo, array $errors): void {
        $bitacora = Bitacora::create([
            'archivo' => $importFile,
            'importacion_log' => json_encode($importInfo),
        ]);

        foreach ($errors as $error) {
            Error::create([
                'bitacora_id' => $bitacora->id,
                'error_log' => json_encode($error),
            ]);
        }
    }

    protected function getFailuresEntry(array $failures): array
    {
        $errors = [];
        foreach ($failures as $failure) {
            $errors[] = [
                'category' => 'validation',
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
        }

        return $errors;
    }

    protected function getFilesToImport(): array
    {
        $filesToImport = array_filter(Storage::disk('imports')->allFiles(),
            fn($file) => preg_match('/^report_\d+\.txt$/', $file));

        return array_filter($filesToImport, fn ($file) =>
                    !Bitacora::where('archivo', $file)
                        ->whereJsonContains('importacion_log->estatus', 'importado')
                        ->exists()
                );
    }

    protected function sendEmailNotification(string $message): void
    {
        Notification::route('mail', [
            'vinkos-pt-admin@test.com' => 'Admin',
        ])->notify(new ImportFileNotification($message));
    }
}
