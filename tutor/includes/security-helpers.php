<?php
/**
 * Security Helper Functions
 * XSS Prevention and Input Validation
 */

/**
 * Escape output to prevent XSS attacks
 * @param string $variable The variable to escape
 * @param string $context The context (html, js, attr, url)
 * @return string Escaped string safe for output
 */
function escape($variable, $context = 'html') {
    if ($variable === null || $variable === false) {
        return '';
    }

    switch ($context) {
        case 'html':
            return htmlspecialchars($variable, ENT_QUOTES, 'UTF-8');
        case 'js':
            return json_encode($variable);
        case 'attr':
            return htmlspecialchars($variable, ENT_QUOTES, 'UTF-8');
        case 'url':
            return urlencode($variable);
        default:
            return htmlspecialchars($variable, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Sanitize plain text input (removes HTML tags and special chars that could be used in attacks)
 * @param string $input The input to sanitize
 * @param int $maxLength Maximum allowed length
 * @return string Sanitized string
 */
function sanitizeInput($input, $maxLength = null) {
    if ($input === null || $input === false) {
        return '';
    }

    // Remove all HTML tags
    $input = strip_tags($input);
    
    // Trim whitespace
    $input = trim($input);
    
    // Apply length restriction if specified
    if ($maxLength !== null && strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }

    return $input;
}
?>
