<?php
declare(strict_types=1);

/** A token belongs to one rendered form, not to the whole login session. */
function widmsNewFormSubmissionToken(): string
{
    $tokens = $_SESSION['widms_form_submission_tokens'] ?? [];
    $cutoff = time() - 4 * 60 * 60;
    foreach ($tokens as $token => $createdAt) {
        if (!is_int($createdAt) || $createdAt < $cutoff) {
            unset($tokens[$token]);
        }
    }
    if (count($tokens) > 3000) {
        asort($tokens, SORT_NUMERIC);
        $tokens = array_slice($tokens, -2000, null, true);
    }
    $token = bin2hex(random_bytes(24));
    $tokens[$token] = time();
    $_SESSION['widms_form_submission_tokens'] = $tokens;
    return $token;
}

function widmsFormSubmissionField(): string
{
    return '<input type="hidden" name="widms_submission_token" value="'
        . widmsNewFormSubmissionToken() . '">';
}

function widmsConsumeFormSubmissionToken(mixed $token): bool
{
    if (!is_string($token) || preg_match('/\A[a-f0-9]{48}\z/D', $token) !== 1) {
        return false;
    }
    $tokens = $_SESSION['widms_form_submission_tokens'] ?? [];
    if (!isset($tokens[$token]) || $tokens[$token] < time() - 4 * 60 * 60) {
        return false;
    }
    unset($tokens[$token]);
    $_SESSION['widms_form_submission_tokens'] = $tokens;
    return true;
}

/** Add a separate one-time token to each server-rendered POST form. */
function widmsAddFormSubmissionFields(string $html): string
{
    $parts = preg_split('/(<script\b[^>]*>.*?<\/script>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }
    foreach ($parts as $index => $part) {
        if ($index % 2 === 1) {
            continue;
        }
        $parts[$index] = preg_replace_callback('/<form\b[^>]*>/i', static function (array $match): string {
            if (preg_match('/\bmethod\s*=\s*(["\']?)post\1(?=\s|>|\/|$)/i', $match[0]) !== 1) {
                return $match[0];
            }
            return $match[0] . widmsFormSubmissionField();
        }, $part) ?? $part;
    }
    return implode('', $parts);
}
