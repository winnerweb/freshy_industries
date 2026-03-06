<?php
declare(strict_types=1);

function cleanText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return $value;
}

function validateContactPayload(array $input): array
{
    $errors = [];

    $name = cleanText((string) ($input['name'] ?? ''));
    $email = cleanText((string) ($input['email'] ?? ''));
    $subject = cleanText((string) ($input['subject'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $whatsapp = cleanText((string) ($input['whatsapp_number'] ?? ''));
    $honeypot = cleanText((string) ($input['website'] ?? ''));
    $csrfToken = cleanText((string) ($input['csrf_token'] ?? ''));

    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Le nom doit contenir entre 2 et 120 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Adresse email invalide.';
    }

    if ($subject === '' || mb_strlen($subject) < 2 || mb_strlen($subject) > 160) {
        $errors['subject'] = 'Le sujet doit contenir entre 2 et 160 caracteres.';
    }

    if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        $errors['message'] = 'Le message doit contenir entre 10 et 5000 caracteres.';
    }

    if ($whatsapp !== '' && !preg_match('/^\+?[0-9\s\-()]{8,30}$/', $whatsapp)) {
        $errors['whatsapp_number'] = 'Numero WhatsApp invalide.';
    }

    if ($honeypot !== '') {
        $errors['website'] = 'Requete invalide.';
    }

    if ($csrfToken === '') {
        $errors['csrf_token'] = 'Jeton CSRF manquant.';
    }

    return [
        'data' => [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'whatsapp_number' => $whatsapp,
            'website' => $honeypot,
            'csrf_token' => $csrfToken,
        ],
        'errors' => $errors,
    ];
}

function validateQuotePayload(array $input): array
{
    $errors = [];

    $customerName = cleanText((string) ($input['customer_name'] ?? ''));
    $phone = cleanText((string) ($input['phone'] ?? ''));
    $email = cleanText((string) ($input['email'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $csrfToken = cleanText((string) ($input['csrf_token'] ?? ''));
    $honeypot = cleanText((string) ($input['website'] ?? ''));
    $rawProducts = $input['products'] ?? [];

    if ($customerName === '' || mb_strlen($customerName) < 2 || mb_strlen($customerName) > 160) {
        $errors['customer_name'] = 'Le nom client doit contenir entre 2 et 160 caracteres.';
    }

    if ($phone === '' || !preg_match('/^\+?[0-9\s\-()]{8,30}$/', $phone)) {
        $errors['phone'] = 'Numero de telephone invalide.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors['email'] = 'Adresse email invalide.';
    }

    if ($message === '' || mb_strlen($message) < 5 || mb_strlen($message) > 2000) {
        $errors['message'] = 'Le message doit contenir entre 5 et 2000 caracteres.';
    }

    if ($csrfToken === '') {
        $errors['csrf_token'] = 'Jeton CSRF manquant.';
    }

    if ($honeypot !== '') {
        $errors['website'] = 'Requete invalide.';
    }

    $normalizedProducts = [];
    if (!is_array($rawProducts) || $rawProducts === []) {
        $errors['products'] = 'Ajoutez au moins un produit.';
    } else {
        foreach ($rawProducts as $index => $row) {
            if (!is_array($row)) {
                $errors["products.$index"] = 'Ligne produit invalide.';
                continue;
            }
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($productId <= 0) {
                $errors["products.$index.product_id"] = 'Produit invalide.';
                continue;
            }
            if ($quantity <= 0 || $quantity > 100000) {
                $errors["products.$index.quantity"] = 'Quantite invalide.';
                continue;
            }
            if (!isset($normalizedProducts[$productId])) {
                $normalizedProducts[$productId] = 0;
            }
            $normalizedProducts[$productId] += $quantity;
        }
    }

    if ($normalizedProducts === [] && !isset($errors['products'])) {
        $errors['products'] = 'Ajoutez au moins un produit valide.';
    }

    $productLines = [];
    foreach ($normalizedProducts as $productId => $quantity) {
        $productLines[] = [
            'product_id' => (int) $productId,
            'quantity' => (int) $quantity,
        ];
    }

    return [
        'data' => [
            'customer_name' => $customerName,
            'phone' => $phone,
            'email' => $email,
            'message' => $message,
            'csrf_token' => $csrfToken,
            'website' => $honeypot,
            'products' => $productLines,
        ],
        'errors' => $errors,
    ];
}
