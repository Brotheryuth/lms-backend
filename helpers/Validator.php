<?php

class Validator {
    private array $errors = [];

    public function required(string $field, $value): self {
        if ($value === null || trim((string)$value) === "") {
            $this->errors[$field] = "$field is required";
        }
        return $this;
    }

    public function email(string $field, $value): self {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$field must be a valid email";
        }
        return $this;
    }

    public function minLength(string $field, $value, int $min): self {
        if ($value && strlen((string)$value) < $min) {
            $this->errors[$field] = "$field must be at least $min characters";
        }
        return $this;
    }

    public function numeric(string $field, $value): self {
        if ($value !== null && $value !== "" && !is_numeric($value)) {
            $this->errors[$field] = "$field must be a number";
        }
        return $this;
    }

    public function inArray(string $field, $value, array $allowed): self {
        if ($value && !in_array($value, $allowed)) {
            $this->errors[$field] = "$field must be one of: " . implode(", ", $allowed);
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }
}