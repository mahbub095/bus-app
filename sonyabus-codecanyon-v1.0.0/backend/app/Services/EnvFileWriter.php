<?php

namespace App\Services;

/**
 * Updates key/value pairs in the application .env file.
 */
class EnvFileWriter
{
    public function set(array $variables): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        // Security: Prevent path traversal
        $realEnvPath = realpath($envPath);
        $realBasePath = realpath(base_path());

        if ($realEnvPath === false || ! str_starts_with($realEnvPath, $realBasePath)) {
            throw new \RuntimeException('Invalid .env file path');
        }

        $content = file_get_contents($envPath);

        foreach ($variables as $key => $value) {
            // Security: Only allow valid env key names (alphanumeric and underscore)
            if (! preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }

            $formattedValue = $this->formatValue((string) $value);
            $keyPattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

            if (preg_match($keyPattern, $content)) {
                $content = preg_replace($keyPattern, $key.'='.$formattedValue, $content);
            } else {
                $content .= "\n".$key.'='.$formattedValue;
            }
        }

        file_put_contents($envPath, $content);
    }

    private function formatValue(string $value): string
    {
        if (preg_match('/\s/i', $value) || str_contains($value, '{') || str_contains($value, '}')) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
