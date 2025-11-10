<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EmailParserService
{
    private const MAX_DEPTH = 8;

    /**
     * Parse raw email content to clean plain text
     */
    public function parseEmailToPlainText(string $rawEmail): string
    {
        try {
            //Not multipart, base64 encoded
            $text = $this->extractSimpleBase64Body($rawEmail);
            if ($this->isValidText($text)) {
                return $this->cleanText($text);
            }

            //Multipart email parsing (recursive)
            $text = $this->extractTextPartRecursive($rawEmail, 0);
            if ($this->isValidText($text)) {
                return $this->cleanText($text);
            }

            //Quoted-printable or HTML
            $text = $this->extractSimpleEmail($rawEmail);
            if ($this->isValidText($text)) {
                return $this->cleanText($text);
            }

            //Extract from all boundaries (handles complex nested structures)
            $text = $this->extractFromAllBoundaries($rawEmail);
            if ($this->isValidText($text)) {
                return $this->cleanText($text);
            }

            //Check if email contains only images/attachments
            if ($this->hasAttachments($rawEmail)) {
                return '[Email contains only images/attachments - no text content]';
            }

            // Finally
            return '[Unable to parse email content]';
        } catch (\Throwable $e) {
            Log::error('EmailParserService error', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return '[Unable to parse email content]';
        }
    }

    /**
     * Check if email has attachments indicator
     */
    private function hasAttachments(string $rawEmail): bool
    {
        return preg_match('/X-MS-Has-Attach:\s*yes/i', $rawEmail) === 1;
    }

    /**
     * Extract body from simple base64-encoded emails (NOT multipart)
     */
    private function extractSimpleBase64Body(string $rawEmail): ?string
    {
        $headerEnd = strpos($rawEmail, "\r\n\r\n");
        if ($headerEnd === false) {
            $headerEnd = strpos($rawEmail, "\n\n");
            if ($headerEnd === false) return null;
            $separatorLength = 2;
        } else {
            $separatorLength = 4;
        }

        $headers = substr($rawEmail, 0, $headerEnd);
        $body = substr($rawEmail, $headerEnd + $separatorLength);

        // Must be base64 but NOT multipart
        if (!preg_match('/Content-Transfer-Encoding:\s*base64/i', $headers)) {
            return null;
        }
        if (preg_match('/Content-Type:.*multipart/i', $headers)) {
            return null;
        }

        // Remove boundary markers (if any)
        $body = preg_replace('/--[a-zA-Z0-9_\-]+.*/s', '', $body);

        // Remove all whitespace and decode
        $cleanBody = preg_replace('/\s+/', '', trim($body));

        $decoded = base64_decode($cleanBody, true);
        if (!$decoded || strlen($decoded) < 20) {
            return null;
        }

        // Convert HTML to text if needed
        if (stripos($decoded, '<html') !== false || stripos($decoded, '<HTML') !== false) {
            return $this->htmlToText($decoded);
        }

        return $decoded;
    }

    /**
     * Extract body from simple emails (NOT multipart, NOT base64)
     */
    private function extractSimpleEmail(string $rawEmail): ?string
    {
        $parts = $this->splitHeadersAndBody($rawEmail);
        $headers = $parts['headers'];
        $body = $parts['body'];

        // Skip multipart emails
        if (preg_match('/Content-Type:.*multipart/i', $headers)) {
            return null;
        }

        // Get encoding and content type
        $encoding = $this->getTransferEncoding($headers);
        $contentType = $this->getContentType($headers);

        // Decode body
        $decodedBody = $this->decodeBody($body, $encoding);

        // Handle HTML content
        if (stripos($contentType, 'text/html') !== false) {
            return $this->htmlToText($decodedBody);
        }

        // Handle plain text
        if (stripos($contentType, 'text/plain') !== false) {
            return $decodedBody;
        }

        // Detect HTML in body
        if (preg_match('/<html|<body|<div/i', $decodedBody)) {
            return $this->htmlToText($decodedBody);
        }

        return $decodedBody;
    }

    /**
     * Extract from all boundaries - handles complex nested structures
     */
    private function extractFromAllBoundaries(string $content): ?string
    {
        // Find all boundaries in the content
        preg_match_all('/boundary=["\']?([^"\';\s\r\n]+)/i', $content, $matches);
        $boundaries = array_unique($matches[1]);

        if (empty($boundaries)) {
            return null;
        }

        // Collect all parts from all boundaries
        $allParts = [];
        foreach ($boundaries as $boundary) {
            $pattern = '/--' . preg_quote($boundary, '/') . '(?:--)?/';
            $parts = preg_split($pattern, $content);
            foreach ($parts as $part) {
                $part = trim($part);
                if (!empty($part) && stripos($part, 'Content-Type:') !== false) {
                    $allParts[] = $part;
                }
            }
        }

        // First pass: Look for valid text/plain (skip CID-only and empty content)
        foreach ($allParts as $part) {
            if (preg_match('/name=|filename=/i', $part)) continue;
            if (preg_match('/Content-Type:\s*image\//i', $part)) continue;

            $partData = $this->splitHeadersAndBody($part);
            $partHeaders = $partData['headers'];
            $partBody = $partData['body'];

            if (preg_match('/Content-Type:\s*text\/plain/i', $partHeaders)) {
                $encoding = $this->getTransferEncoding($partHeaders);
                $decoded = $this->decodeBody($partBody, $encoding);
                $cleaned = $this->cleanText($decoded);

                // Skip if only CID references or empty
                if ($this->isEmptyOrCidOnly($cleaned)) {
                    continue;
                }

                if ($this->isValidText($cleaned)) {
                    return $cleaned;
                }
            }
        }

        // Second pass: Use HTML if no valid text/plain found
        foreach ($allParts as $part) {
            if (preg_match('/name=|filename=/i', $part)) continue;
            if (preg_match('/Content-Type:\s*image\//i', $part)) continue;

            $partData = $this->splitHeadersAndBody($part);
            $partHeaders = $partData['headers'];
            $partBody = $partData['body'];

            if (preg_match('/Content-Type:\s*text\/html/i', $partHeaders)) {
                $encoding = $this->getTransferEncoding($partHeaders);
                $decoded = $this->decodeBody($partBody, $encoding);
                $htmlText = $this->htmlToText($decoded);
                $cleaned = $this->cleanText($htmlText);

                // Check if HTML contains actual text
                if ($this->isValidText($cleaned)) {
                    return $cleaned;
                }
            }
        }

        return null;
    }

    /**
     * Check if text is empty or contains only CID references
     */
    private function isEmptyOrCidOnly(string $text): bool
    {
        // Text is empty
        if (trim($text) === '') {
            return true;
        }

        // Check if only contains CID pattern
        $pattern = '/^\[cid:[^\]]+\]$/';
        if (preg_match($pattern, trim($text))) {
            return true;
        }

        return false;
    }

    /**
     * Recursively extract text parts from multipart emails
     */
    private function extractTextPartRecursive(string $content, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) return null;

        // Find boundary
        if (!preg_match('/boundary=["\']?([^"\';\s\r\n]+)/i', $content, $m)) {
            return null;
        }

        $boundary = trim($m[1]);
        $pattern = '/--' . preg_quote($boundary, '/') . '(?:--)?/';
        $parts = preg_split($pattern, $content);

        // First pass: Look for text/plain
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (!stripos($part, 'Content-Type:')) continue;
            if (preg_match('/name=|filename=/i', $part)) continue;
            if (preg_match('/Content-Type:\s*image\//i', $part)) continue;

            $partData = $this->splitHeadersAndBody($part);
            $partHeaders = $partData['headers'];
            $partBody = $partData['body'];

            // Check for text/plain
            if (preg_match('/Content-Type:\s*text\/plain/i', $partHeaders)) {
                $encoding = $this->getTransferEncoding($partHeaders);
                $decoded = $this->decodeBody($partBody, $encoding);
                $cleaned = $this->cleanText($decoded);

                // Skip if empty or contains only CID references
                if ($this->isEmptyOrCidOnly($cleaned)) {
                    continue;
                }

                if ($this->isValidText($cleaned)) {
                    return $cleaned;
                }
            }

            // Check for nested multipart
            if (stripos($partHeaders, 'multipart/') !== false) {
                $nested = $this->extractTextPartRecursive($part, $depth + 1);
                if ($this->isValidText($nested)) {
                    return $nested;
                }
            }
        }

        // Second pass: Use HTML if no valid text/plain found
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (preg_match('/name=|filename=/i', $part)) continue;
            if (preg_match('/Content-Type:\s*image\//i', $part)) continue;

            $partData = $this->splitHeadersAndBody($part);
            $partHeaders = $partData['headers'];
            $partBody = $partData['body'];

            if (preg_match('/Content-Type:\s*text\/html/i', $partHeaders)) {
                $encoding = $this->getTransferEncoding($partHeaders);
                $decoded = $this->decodeBody($partBody, $encoding);
                $htmlText = $this->htmlToText($decoded);
                $cleaned = $this->cleanText($htmlText);

                // Check if HTML contains actual text
                if ($this->isValidText($cleaned)) {
                    return $cleaned;
                }
            }
        }

        return null;
    }

    /**
     * Split email into headers and body
     */
    private function splitHeadersAndBody(string $rawEmail): array
    {
        $headerEnd = strpos($rawEmail, "\r\n\r\n");
        if ($headerEnd === false) {
            $headerEnd = strpos($rawEmail, "\n\n");
            if ($headerEnd === false) {
                return ['headers' => $rawEmail, 'body' => ''];
            }
            $separatorLength = 2;
        } else {
            $separatorLength = 4;
        }

        return [
            'headers' => substr($rawEmail, 0, $headerEnd),
            'body' => substr($rawEmail, $headerEnd + $separatorLength)
        ];
    }

    /**
     * Extract Content-Transfer-Encoding from headers
     */
    private function getTransferEncoding(string $headers): ?string
    {
        if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $headers, $m)) {
            return strtolower(trim($m[1]));
        }
        return null;
    }

    /**
     * Extract Content-Type from headers
     */
    private function getContentType(string $headers): string
    {
        if (preg_match('/Content-Type:\s*([^;\r\n]+)/i', $headers, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Decode email body based on transfer encoding
     */
    private function decodeBody(string $body, ?string $encoding): string
    {
        if (!$encoding) {
            return $body;
        }

        switch ($encoding) {
            case 'base64':
                $decoded = base64_decode(preg_replace('/\s+/', '', $body), true);
                return $decoded !== false ? $decoded : $body;

            case 'quoted-printable':
                return quoted_printable_decode($body);

            case '7bit':
            case '8bit':
            case 'binary':
            default:
                return $body;
        }
    }

    /**
     * Check if text is valid (not empty, sufficient length)
     */
    private function isValidText(?string $text): bool
    {
        if ($text === null) return false;

        $trimmed = trim($text);
        if (strlen($trimmed) < 10) return false;

        // Check if text contains actual content (not just whitespace/special chars)
        $contentCheck = preg_replace('/[\s\n\r\t\-_=.]+/', '', $trimmed);
        return strlen($contentCheck) > 5;
    }

    /**
     * Convert HTML to plain text
     */
    private function htmlToText(string $html): string
    {
        // 1. Decode quoted-printable if present
        $html = quoted_printable_decode($html);

        // 2. Remove scripts, styles, and head
        $html = preg_replace('/<(script|style|head)[^>]*>.*?<\/\1>/is', '', $html);

        // 3. Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // 4. Remove tags that typically don't contain content
        $html = preg_replace('/<(meta|link|base|noscript|img)[^>]*>/i', '', $html);

        // 5. Preserve basic formatting
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|tr|h[1-6]|li|td|th)>/i', "\n", $html);
        $html = preg_replace('/<(p|div|tr|h[1-6]|li|td|th)[^>]*>/i', "\n", $html);

        // 6. Remove HTML tabs
        $html = preg_replace('/\t+/', ' ', $html);

        // 7. Convert links (remove tags, keep text only)
        $html = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '$2', $html);

        // 8. Remove all remaining tags
        $text = strip_tags($html);

        // 9. Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 10. Remove invisible Unicode characters
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}\x{180E}\x{061C}]/u', '', $text);

        // 11. Replace non-breaking spaces with regular spaces
        $text = str_replace("\xC2\xA0", ' ', $text);

        return trim($text);
    }

    /**
     * Clean and normalize extracted text
     */
    private function cleanText(string $text): string
    {
        // 1. Remove boundary markers
        $text = preg_replace('/^--[a-zA-Z0-9_\-]+.*$/m', '', $text);

        // 2. Remove CID references
        $text = preg_replace('/\[cid:[^\]]+\]/i', '', $text);

        // 3. Remove MIME headers
        $text = preg_replace('/^(Content-[^:]+:.*)$/mi', '', $text);

        // 4. Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text);

        // 5. Remove invisible Unicode characters
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}\x{180E}\x{061C}]/u', '', $text);

        // 6. Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 7. Replace all tabs with single spaces
        $text = str_replace("\t", ' ', $text);

        // 8. Remove leading spaces from each line
        $text = preg_replace('/^[ ]+/m', '', $text);

        // 9. Remove trailing spaces from each line
        $text = preg_replace('/[ ]+$/m', '', $text);

        // 10. Remove multiple spaces within lines
        $text = preg_replace('/ {2,}/', ' ', $text);

        // 11. Limit to maximum 2 consecutive empty lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // 12. Clean lines
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function($line) {
            if ($line === '') return false;

            // Remove lines consisting of only one character
            if (strlen($line) === 1 && in_array($line, ['.', '-', '_', '=', '|', '>', '<', '*'])) {
                return false;
            }

            // Remove lines with only repeated characters (max 3)
            if (preg_match('/^[\-_=\.]{1,3}$/', $line)) return false;
            if (preg_match('/^[=\s]*$/', $line)) return false;

            return true;
        });

        // 13. Reassemble
        return trim(implode("\n", $lines));
    }
}
