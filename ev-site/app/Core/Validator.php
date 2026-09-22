<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Schema-driven validator.
 *
 * Rule keys: type (string|int|float|bool|enum|email|url), required, nullable,
 * min, max (numeric bounds or string length), values (enum), default.
 */
final class Validator
{
    /**
     * @param array $input   raw input
     * @param array $schema  field => rules
     * @param bool  $partial when true (updates), missing fields are skipped
     * @return array cleaned data
     */
    public static function validate(array $input, array $schema, bool $partial = false): array
    {
        $out = [];
        $errors = [];

        foreach ($schema as $field => $rules) {
            $present = array_key_exists($field, $input);
            if (!$present) {
                if ($partial) {
                    continue;
                }
                if (!empty($rules['required'])) {
                    $errors[$field] = 'This field is required.';
                    continue;
                }
                if (array_key_exists('default', $rules)) {
                    $out[$field] = $rules['default'];
                }
                continue;
            }

            $value = $input[$field];
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                if (!empty($rules['required'])) {
                    $errors[$field] = 'This field is required.';
                } elseif ($rules['type'] === 'bool') {
                    $out[$field] = 0;
                } else {
                    $out[$field] = null;
                }
                continue;
            }

            $error = null;
            $clean = self::cast($value, $rules, $error);
            if ($error !== null) {
                $errors[$field] = $error;
            } else {
                $out[$field] = $clean;
            }
        }

        if ($errors) {
            throw new HttpException(422, 'Please correct the highlighted fields.', $errors);
        }
        return $out;
    }

    private static function cast($value, array $rules, ?string &$error)
    {
        $min = $rules['min'] ?? null;
        $max = $rules['max'] ?? null;

        switch ($rules['type']) {
            case 'int':
                if (!is_numeric($value) || floor((float) $value) != (float) $value) {
                    $error = 'Must be a whole number.';
                    return null;
                }
                $v = (int) $value;
                break;
            case 'float':
                if (!is_numeric($value)) {
                    $error = 'Must be a number.';
                    return null;
                }
                $v = (float) $value;
                break;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            case 'enum':
                if (!in_array($value, $rules['values'], true)) {
                    $error = 'Must be one of: ' . implode(', ', $rules['values']) . '.';
                    return null;
                }
                return $value;
            case 'email':
                $v = strtolower((string) $value);
                if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 190) {
                    $error = 'Must be a valid email address.';
                    return null;
                }
                return $v;
            case 'url':
                $v = (string) $value;
                $isRelative = strncmp($v, '/', 1) === 0 && strncmp($v, '//', 2) !== 0;
                $isHttp = (bool) preg_match('#^https?://#i', $v) && filter_var($v, FILTER_VALIDATE_URL);
                if ((!$isRelative && !$isHttp) || strlen($v) > ($max ?? 500)) {
                    $error = 'Must be a valid http(s) URL or site-relative path.';
                    return null;
                }
                return $v;
            case 'string':
            default:
                if (!is_scalar($value)) {
                    $error = 'Invalid value.';
                    return null;
                }
                $v = (string) $value;
                $len = mb_strlen($v);
                if ($min !== null && $len < $min) {
                    $error = "Must be at least $min characters.";
                    return null;
                }
                if ($max !== null && $len > $max) {
                    $error = "Must be at most $max characters.";
                    return null;
                }
                return $v;
        }

        if ($min !== null && $v < $min) {
            $error = "Must be at least $min.";
            return null;
        }
        if ($max !== null && $v > $max) {
            $error = "Must be at most $max.";
            return null;
        }
        return $v;
    }
}
