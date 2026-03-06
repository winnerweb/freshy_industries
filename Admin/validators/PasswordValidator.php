<?php
declare(strict_types=1);

final class PasswordValidator
{
    public function validate(string $newPassword): ?string
    {
        if (strlen($newPassword) < 8) {
            return 'Le mot de passe doit contenir au moins 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            return 'Le mot de passe doit contenir une majuscule.';
        }
        if (!preg_match('/[a-z]/', $newPassword)) {
            return 'Le mot de passe doit contenir une minuscule.';
        }
        if (!preg_match('/\d/', $newPassword)) {
            return 'Le mot de passe doit contenir un chiffre.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
            return 'Le mot de passe doit contenir un caractere special.';
        }

        return null;
    }
}
