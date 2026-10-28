<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helper.php';

require_login($conn);

if (!$conn) {
    die("Database connection failed.");
}

$filename = "students_export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['ID', 'Student ID No', 'Name', 'Email', 'Mobile', 'Department', 'Semester', 'CGPA', 'Status', 'Created At']);

$res = $conn->query("SELECT id, student_id_no, name, email, mobile, department, semester, cgpa, status, created_at FROM students WHERE status = 'Active' ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['student_id_no'],
            $row['name'],
            $row['email'],
            $row['mobile'],
            $row['department'],
            $row['semester'],
            $row['cgpa'],
            $row['status'],
            $row['created_at']
        ]);
    }
}

fclose($output);
exit();
