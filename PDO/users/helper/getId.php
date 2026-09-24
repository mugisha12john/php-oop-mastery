<?php
function getPositiveIntFromGetId(string $key): int
{
    if (!isset($_GET[$key])) {
        throw new InvalidArgumentException("Missing parameter: $key");
    }

    $value = filter_var($_GET[$key], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if ($value === false) {
        throw new InvalidArgumentException("Invalid $key: must be a positive integer.");
    }

    return $value;
}

?>