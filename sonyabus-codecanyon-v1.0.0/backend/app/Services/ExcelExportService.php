<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    public function download(array $headers, array $rows, string $filename, string $title = 'Report'): StreamedResponse
    {
        $xml = $this->buildSpreadsheetXml($headers, $rows, $title);

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename . '.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    protected function buildSpreadsheetXml(array $headers, array $rows, string $title): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_XML1);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $xml .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Worksheet ss:Name="' . $escapedTitle . '"><Table>' . "\n";

        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= $this->cell($header, true);
        }
        $xml .= '</Row>' . "\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $value) {
                $xml .= $this->cell($value);
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }

    protected function cell(mixed $value, bool $isHeader = false): string
    {
        if (is_numeric($value) && ! is_string($value)) {
            return '<Cell><Data ss:Type="Number">' . $value . '</Data></Cell>';
        }

        if (is_string($value) && is_numeric($value) && ! preg_match('/^0\d/', $value)) {
            return '<Cell><Data ss:Type="Number">' . $value . '</Data></Cell>';
        }

        $type = $isHeader ? 'String' : 'String';
        $escaped = htmlspecialchars((string) $value, ENT_XML1);

        return '<Cell><Data ss:Type="' . $type . '">' . $escaped . '</Data></Cell>';
    }
}
