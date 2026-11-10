<?php
declare(strict_types=1);

namespace Enlixo\Students;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The template of Upload Students Bulk, made from the tutor's student form:
 *   csv  = the header row only (UTF-8 with BOM so Excel shows Sinhala / Tamil names correctly),
 *   xlsx = sheet "Students" (header row, every cell text so 0771234567 keeps its 0, a drop-down on list columns),
 *          sheet "How to fill" (column, needed, what to write, example) and a hidden sheet with the lists.
 */
final class StudentBulkTemplate
{
    public static function csv(array $columns): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, array_column($columns, 'header'), ',', '"', '');
        rewind($fh);
        $out = "\xEF\xBB\xBF" . stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    /** @param array<string, list<string>> $lists header => choices */
    public static function xlsx(array $columns, array $lists): string
    {
        StudentBulkUpload::spreadsheetLib();
        $book = new Spreadsheet();
        $book->getProperties()->setTitle('Students')->setCreator('ENLIXO Tutor');

        $sheet = $book->getActiveSheet()->setTitle('Students');
        $rows = StudentBulkUpload::MAX_ROWS + 1;
        $listSheet = $book->createSheet()->setTitle('Lists');
        $listCol = 0;
        foreach ($columns as $i => $c) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}1", $c['header']);
            $sheet->getColumnDimension($col)->setWidth(max(14, min(34, mb_strlen($c['header']) + 6)));
            $sheet->getStyle("{$col}2:{$col}$rows")->getNumberFormat()->setFormatCode('@'); // text: phone numbers keep their 0
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $c['required'] ? 'FFFFFF' : '1F2937']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c['required'] ? '2563EB' : 'E5EDFB']],
            ]);
            $sheet->getComment("{$col}1")->getText()->createText(($c['required'] ? "Required.\n" : "Optional.\n") . $c['help'] . ($c['example'] !== '' ? "\nFor example: {$c['example']}" : ''));

            $choices = $lists[$c['header']] ?? [];
            if ($choices) {
                $lc = Coordinate::stringFromColumnIndex(++$listCol);
                $listSheet->setCellValue("{$lc}1", $c['header']);
                foreach (array_values($choices) as $j => $choice) {
                    $listSheet->setCellValueExplicit($lc . ($j + 2), $choice, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $dv = new DataValidation();
                $dv->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                    ->setErrorStyle(DataValidation::STYLE_WARNING)->setErrorTitle('Not in the list')->setError('Choose one of the names in the list.')
                    ->setFormula1("'Lists'!\${$lc}\$2:\${$lc}\$" . (count($choices) + 1));
                $sheet->setDataValidation("{$col}2:{$col}$rows", $dv);
            }
        }
        $sheet->freezePane('A2');
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $help = $book->createSheet(1)->setTitle('How to fill');
        $help->fromArray(['Column', 'Needed', 'What to write', 'Example'], null, 'A1');
        foreach ($columns as $i => $c) {
            $help->fromArray([$c['header'], $c['required'] ? 'Required' : 'Optional', $c['help'], $c['example']], null, 'A' . ($i + 2));
            $help->getCell('D' . ($i + 2))->setValueExplicit($c['example'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $help->getStyle('A1:D1')->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5EDFB']]]);
        foreach (['A' => 28, 'B' => 12, 'C' => 80, 'D' => 26] as $col => $w) {
            $help->getColumnDimension($col)->setWidth($w);
        }
        $help->getStyle('C:C')->getAlignment()->setWrapText(true);
        $book->setActiveSheetIndex(0);

        $fh = fopen('php://temp', 'r+');
        (new Xlsx($book))->save($fh);
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);
        return $out;
    }
}
