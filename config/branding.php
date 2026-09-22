<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Dukame',
    'tagline' => $_ENV['APP_TAGLINE'] ?? 'A simpler way to sell online',
    'primary' => $_ENV['APP_PRIMARY_COLOR'] ?? '#5B21B6',
    'accent' => $_ENV['APP_ACCENT_COLOR'] ?? '#F59E0B',
];
