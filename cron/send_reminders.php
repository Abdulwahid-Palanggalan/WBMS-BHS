<?php
/**
 * cron/send_reminders.php
 * Automated script to send SMS reminders for appointments 24 hours in advance.
 * Run this via a system cron job: 0 8 * * * (Every morning at 8:00 AM)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/SMSService.php';

global $pdo;
$sms = new SMSService(); // Uses mock mode by default
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$count = 0;

echo "--- SMS Reminder Job [".date('Y-m-d H:i:s')."] ---\n";

// 1. Prenatal Appointment Reminders
$stmt = $pdo->prepare("
    SELECT u.first_name, u.phone, pr.visit_date
    FROM prenatal_records pr
    JOIN mothers m ON pr.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE pr.visit_date = ?
");
$stmt->execute([$tomorrow]);
$prenatals = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($prenatals as $row) {
    $msg = SMSService::getAppointmentTemplate($row['first_name'], $row['visit_date'], 'prenatal');
    if ($sms->send($row['phone'], $msg)) {
        $count++;
    }
}

// 2. Immunization Reminders
$stmt = $pdo->prepare("
    SELECT u.first_name as mother_name, u.phone, ir.next_dose_date, br.first_name as baby_name
    FROM immunization_records ir
    JOIN birth_records br ON ir.baby_id = br.id
    JOIN mothers m ON br.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE ir.next_dose_date = ?
");
$stmt->execute([$tomorrow]);
$immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($immunizations as $row) {
    $msg = "Hi {$row['mother_name']}, this is a reminder for {$row['baby_name']}'s vaccination scheduled for tomorrow, {$row['next_dose_date']}.";
    if ($sms->send($row['phone'], $msg)) {
        $count++;
    }
}

// 3. Family Planning Reminders
$stmt = $pdo->prepare("
    SELECT u.first_name, u.phone, fpr.next_service_date
    FROM family_planning_records fpr
    JOIN mothers m ON fpr.mother_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE fpr.next_service_date = ?
");
$stmt->execute([$tomorrow]);
$fps = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($fps as $row) {
    $msg = SMSService::getAppointmentTemplate($row['first_name'], $row['next_service_date'], 'family planning');
    if ($sms->send($row['phone'], $msg)) {
        $count++;
    }
}

echo "Job finished. Total reminders sent: $count\n";
