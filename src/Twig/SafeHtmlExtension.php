<?php

namespace App\Twig;

use HTMLPurifier;
use HTMLPurifier_Config;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SafeHtmlExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_html', [$this, 'sanitize']),
        ];
    }

    public function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,ul,ol,li,a[href|title|target|rel],h2,h3,h4,blockquote');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);

        return (new HTMLPurifier($config))->purify($html);
    }
}
