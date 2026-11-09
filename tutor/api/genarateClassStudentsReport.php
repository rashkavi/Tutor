<?php
header('Content-Type: application/json'); // Set JSON header at the very start (overridden below on success)

$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once __DIR__ . '/../../vendor/autoload.php'; // adjust path to your composer autoload

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);

        if (json_last_error() != JSON_ERROR_NONE) {
            $status->error = 'Invalid JSON';
            http_response_code(400);
            echo json_encode($status);
            exit();
        }

        $class_id = isset($decoded['class_id']) ? $decoded['class_id'] : 0;
        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;

        // Report info-row fields (shown above the column headers)
        $subject_name = isset($decoded['subject']) ? $decoded['subject'] : '';
        $year_value = isset($decoded['year']) ? $decoded['year'] : '';
        $medium_value = isset($decoded['medium']) ? $decoded['medium'] : '';
        $exam_name = isset($decoded['exam']) ? $decoded['exam'] : '';

        // Resolve the selected class's name (falls back to "All Classes" when no filter applied)
        $selected_class_name = 'All Classes';
        if ($class_id != 0) {
            $classResult = Database::search("SELECT * FROM `classes` 
            INNER JOIN `subjects` ON `classes`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `year` ON `classes`.`year_year_id` = `year`.`year_id`
            INNER JOIN `medium` ON `classes`.`medium_medium_id` = `medium`.`medium_id`
            INNER JOIN `exam_type` ON `classes`.`exam_type_exam_type_id` = `exam_type`.`exam_type_id`
            WHERE `class_id` = '" . Database::escape($class_id) . "' LIMIT 1");

            if ($classResult && $classResult->num_rows > 0) {
                $classRow = $classResult->fetch_assoc();
                $selected_class_name = $classRow['class_name'];
                $subject_name = $classRow['subject'];
                $year_value = $classRow['year'];
                $medium_value = $classRow['medium'];
                $exam_name = $classRow['exam_type'];
            }
        }

        // Base query. Class details are pulled in via a correlated GROUP_CONCAT subquery
        // so that a student enrolled in multiple classes still returns as a single row.
        $query = "SELECT `students`.*, `login_details`.*, `status`.*, `gender`.*,
                (
                    SELECT GROUP_CONCAT(DISTINCT `classes`.`class_name` ORDER BY `classes`.`class_name` SEPARATOR ', ')
                    FROM `students_has_classes`
                    INNER JOIN `classes` ON `classes`.`class_id` = `students_has_classes`.`classes_class_id`
                    WHERE `students_has_classes`.`students_student_id` = `students`.`student_id`
                ) AS `class_names`
                FROM `students`
                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                INNER JOIN `status` ON `login_details`.`status_status_id`=`status`.`status_id`
                INNER JOIN `gender` ON `students`.`gender_gender_id`=`gender`.`gender_id`";

        if ($class_id != 0) {
            $query .= " INNER JOIN `students_has_classes` ON `students`.`student_id` = `students_has_classes`.`students_student_id`";
            $query .= " WHERE `students_has_classes`.`classes_class_id` = '" . $class_id . "'";
        }

        if ($search_text != "") {
            if (strpos($query, 'WHERE') == false) {
                $query .= " WHERE (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "' OR `mobile` LIKE '%" . $search_text . "' OR `student_id` LIKE '%" . $search_text . "')";
            } else {
                $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "' OR `mobile` LIKE '%" . $search_text . "' OR `student_id` LIKE '%" . $search_text . "')";
            }
        }

        if ($sort != 0) {
            if ($sort == 1) {
                $query .= " ORDER BY `first_name` ASC";
            } else if ($sort == 2) {
                $query .= " ORDER BY `first_name` DESC";
            } else if ($sort == 3) {
                if (strpos($query, 'WHERE') == false) {
                    $query .= " WHERE `status_status_id` = 1";
                } else {
                    $query .= " AND `status_status_id` = 1";
                }
            } else if ($sort == 4) {
                if (strpos($query, 'WHERE') == false) {
                    $query .= " WHERE `status_status_id` = 2";
                } else {
                    $query .= " AND `status_status_id` = 2";
                }
            } else if ($sort == 5) {
                $query .= " ORDER BY `student_id` DESC";
            } else if ($sort == 6) {
                $query .= " ORDER BY `student_id` ASC";
            }
        }

        if ($sort == 0 || $sort == 3 || $sort == 4) {
            $query .= " ORDER BY `student_id` DESC";
        }

        $students = Database::search($query);

        if ($students->num_rows > 0) {

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Students');

            $headers = [
                'Student Id',
                'First Name',
                'Last Name',
                'Full Name',
                'Name with Initials',
                'Date of Birth',
                'Whatsapp',
                'Gender',
                'Status',
                'Class(es)'
            ];

            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

            // Row 1: report info row (Class / Subject / Year / Medium / Exam)
            $infoParts = [
                'Class: ' . $selected_class_name,
                'Subject: ' . ($subject_name !== '' ? $subject_name : '-'),
                'Year: ' . ($year_value !== '' ? $year_value : '-'),
                'Medium: ' . ($medium_value !== '' ? $medium_value : '-'),
                'Exam: ' . ($exam_name !== '' ? $exam_name : '-'),
            ];
            $infoLine = implode('    |    ', $infoParts);

            $sheet->getCell([1, 1])->setValue($infoLine);
            $sheet->mergeCells("A1:{$lastColLetter}1");
            $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '1F3864'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E2F3'], // light navy/blue tint
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'AAAAAA'],
                    ],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(26);

            // Row 2: actual column headers
            $col = 1;
            foreach ($headers as $headerLabel) {
                $sheet->getCell([$col, 2])->setValue($headerLabel);
                $col++;
            }

            // Style header row (bold white text on navy fill)
            $headerRange = "A2:{$lastColLetter}2";

            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F3864'], // navy
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
            $sheet->getRowDimension(2)->setRowHeight(22);

            // Write data rows (starts after the info row + header row)
            $rowNum = 3;
            $activeFill = 'D9EAD3';   // light green
            $inactiveFill = 'F4CCCC'; // light red
            $altFill = 'F2F2F2';      // light grey (zebra striping)

            while ($student = $students->fetch_assoc()) {
                $rowData = [
                    $student['student_id'],
                    $student['first_name'],
                    $student['last_name'],
                    $student['full_name'],
                    $student['name_with_initials'],
                    $student['date_of_birth'],
                    $student['whatsapp'],
                    $student['gender'],
                    $student['status'],
                    $student['class_names'] ?? ''
                ];

                $col = 1;
                foreach ($rowData as $value) {
                    $sheet->getCell([$col, $rowNum])->setValue($value);
                    $col++;
                }

                $rowRange = "A{$rowNum}:{$lastColLetter}{$rowNum}";

                // Zebra striping as base
                $fillColor = ($rowNum % 2 == 0) ? $altFill : 'FFFFFF';
                $sheet->getStyle($rowRange)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $fillColor],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'DDDDDD'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Highlight status column based on value (green = active-ish, red = inactive-ish)
                $statusVal = strtolower(trim($student['status']));
                $statusCellRange = "I{$rowNum}";
                if (strpos($statusVal, 'active') !== false || strpos($statusVal, 'enroll') !== false) {
                    $sheet->getStyle($statusCellRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($activeFill);
                } elseif (strpos($statusVal, 'inactive') !== false || strpos($statusVal, 'suspend') !== false || strpos($statusVal, 'drop') !== false) {
                    $sheet->getStyle($statusCellRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($inactiveFill);
                }

                $rowNum++;
            }

            // Auto-size all columns
            foreach (range(1, count($headers)) as $colIndex) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            // Freeze the info row + header row, and enable filter dropdowns on the header row
            $sheet->freezePane('A3');
            $sheet->setAutoFilter($headerRange);

            // Clean any accidental output buffer content (avoids "headers already sent" / corrupt xlsx)
            if (ob_get_length()) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="students.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        } else {
            // Return JSON error if no students found
            $status->error = 'No students found! Please refresh filters and try again.';
        }
    } else {
        $status->error = 'Invalid Content-Type';
    }
} else {
    $status->error = 'Invalid request method';
}
if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
