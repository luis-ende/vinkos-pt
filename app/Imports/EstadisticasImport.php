<?php

namespace App\Imports;

use App\Models\Estadistica;
use DateTime;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EstadisticasImport implements ToModel,
    WithCustomCsvSettings,
    WithHeadingRow,
    WithBatchInserts,
    WithValidation,
    SkipsOnError
{
    use Importable, SkipsErrors;

    /**
     * Importación del renglón a un modelo.
     *
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Estadistica([
            'email' => $row['email'],
            'jyv' => $row['jyv'] ?? null,
            'badmail' => $row['badmail'],
            'baja' => $row['baja'],
            'fecha_envio' => DateTime::createFromFormat('d/m/Y H:i', $row['fecha_envio']),
            'fecha_open' => $row['fecha_open'] ?
                DateTime::createFromFormat('d/m/Y H:i', $row['fecha_open']) : null,
            'opens' => $row['opens'],
            'opens_virales' => $row['opens_virales'],
            'fecha_click' => $row['fecha_click'] ?
                DateTime::createFromFormat('d/m/Y H:i', $row['fecha_click']) : null,
            'clicks' => $row['clicks'],
            'clicks_virales' => $row['clicks_virales'],
            'links' => $row['links'],
            'ips' => $row['ips'],
            'navegadores' => $row['navegadores'],
            'plataformas' => $row['plataformas'],
        ]);
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ",",
            'enclosure' => '',
        ];
    }

    public function batchSize(): int
    {
        return 1000;
    }

    /**
     * Reglas de validación aplicables para las columnas a importar.
     *
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'fecha_envio' => 'date_format:d/m/Y H:i',
            'fecha_open' => 'nullable|date_format:d/m/Y H:i',
            'fecha_click' => 'nullable|date_format:d/m/Y H:i',
        ];
    }

    /*
     * Preprocesamiento de algunas columnas antes de la validación.
     */
    public function prepareForValidation($data, $index)
    {
        $data['fecha_envio'] = $data['fecha_envio'] === '-' ? null : $data['fecha_envio'];
        $data['fecha_open'] = $data['fecha_open'] === '-' ? null : $data['fecha_open'];
        $data['fecha_click'] = $data['fecha_click'] === '-' ? null : $data['fecha_click'];

        $data['links'] = $data['links'] === '-' ? null : $data['links'];
        $data['ips'] = $data['ips'] === '-' ? null : $data['ips'];
        $data['navegadores'] = $data['navegadores'] === '-' ? null : $data['navegadores'];
        $data['plataformas'] = $data['plataformas'] === '-' ? null : $data['plataformas'];

        return $data;
    }
}
