<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('contact_submit', 30, 60);

$body = json_read_body();
$name = trim((string) ($body['name'] ?? ''));
$email = strtolower(trim((string) ($body['email'] ?? '')));
$phone = trim((string) ($body['phone'] ?? ''));
$subject = trim((string) ($body['subject'] ?? ''));
$message = trim((string) ($body['message'] ?? ''));

$errors = array();
if ($name === '' || strlen($name) < 2) $errors[] = 'Nom requis.';
if ($message === '' || strlen($message) < 3) $errors[] = 'Message requis.';

$hasPhone = ($phone !== '');
$hasEmail = ($email !== '');
if (!$hasPhone && !$hasEmail) $errors[] = 'Telephone ou email requis.';
if ($hasEmail && !api_is_valid_email($email)) $errors[] = 'Email invalide.';
if ($hasPhone && strlen(api_normalize_phone_digits($phone)) < 8) $errors[] = 'Telephone invalide.';
if ($subject !== '' && strlen($subject) > 80) $subject = substr($subject, 0, 80);

if ($errors) {
  json_response(422, array('ok' => false, 'message' => 'Champs invalides.', 'errors' => $errors));
}

try {
  $pdo = api_pdo();
  $sql = 'INSERT INTO contacts (name, email, phone, subject, message) VALUES (:name, :email, :phone, :subject, :message)';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array(
    'name' => $name,
    'email' => ($email === '' ? null : $email),
    'phone' => ($phone === '' ? null : $phone),
    'subject' => ($subject === '' ? null : $subject),
    'message' => $message,
  ));

  json_response(201, array('ok' => true, 'message' => 'Message envoye.'));
} catch (Throwable $e) {
  error_log('[api/contact_submit] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible denvoyer le message.'));
}

