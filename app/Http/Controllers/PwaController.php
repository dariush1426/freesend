<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PwaController extends Controller
{
    public function manifest(): Response
    {
        $name = (string) Setting::getValue('app_display_name', 'FreeSend');
        $shortName = (string) Setting::getValue('app_short_name', 'FreeSend');
        $themeColor = (string) Setting::getValue('pwa_theme_color', '#0f766e');
        $backgroundColor = (string) Setting::getValue('pwa_background_color', '#eef2f7');

        $mobile = $this->resolveLogoMeta('mobile');
        $desktop = $this->resolveLogoMeta('desktop');
        $retina = $this->resolveLogoMeta('retina');

        $manifest = [
            'name' => $name,
            'short_name' => $shortName,
            'start_url' => '/',
            'id' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $themeColor,
            'background_color' => $backgroundColor,
            'icons' => [
                [
                    'src' => $mobile['url'],
                    'sizes' => $mobile['sizes'],
                    'type' => $mobile['type'],
                    'purpose' => 'any',
                ],
                [
                    'src' => $desktop['url'],
                    'sizes' => $desktop['sizes'],
                    'type' => $desktop['type'],
                    'purpose' => 'any',
                ],
                [
                    'src' => $retina['url'],
                    'sizes' => $retina['sizes'],
                    'type' => $retina['type'],
                    'purpose' => 'any',
                ],
            ],
        ];

        return response(
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/manifest+json; charset=utf-8']
        );
    }

    public function serviceWorker(): Response
    {
        $version = 'freesend-v4';
        $script = <<<JS
const CACHE_NAME = '{$version}';
const APP_SHELL = ['/manifest.webmanifest'];
const STATIC_DESTINATIONS = ['style', 'script', 'image', 'font'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).catch(() => Promise.resolve())
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(async () => {
          const cached = await caches.match(event.request);
          return cached || caches.match('/dashboard') || caches.match('/');
        })
    );

    return;
  }

  if (STATIC_DESTINATIONS.includes(event.request.destination)) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        const networkFetch = fetch(event.request)
          .then((response) => {
            if (!response || !response.ok) {
              return response;
            }

            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone)).catch(() => {});
            return response;
          });

        return cached || networkFetch;
      })
    );

    return;
  }

  event.respondWith(fetch(event.request));
});
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function logo(string $variant): Response
    {
        $config = $this->variantConfig($variant);
        abort_if($config === null, 404);

        $path = (string) Setting::getValue($config['setting_key'], '');

        if ($path !== '' && Storage::exists($path)) {
            return Storage::response($path);
        }

        $defaultPath = public_path($config['default_public_path']);

        if (is_file($defaultPath)) {
            return response(file_get_contents($defaultPath) ?: '', 200, ['Content-Type' => 'image/png']);
        }

        $fallback = public_path('favicon.ico');

        if (is_file($fallback)) {
            return response(file_get_contents($fallback) ?: '', 200, ['Content-Type' => 'image/x-icon']);
        }

        return response('', 404);
    }

    public function landingAsset(string $slot): Response
    {
        $settingKey = match ($slot) {
            'hero' => 'landing_hero_image_path',
            'feature-1' => 'landing_feature_1_image_path',
            'feature-2' => 'landing_feature_2_image_path',
            'feature-3' => 'landing_feature_3_image_path',
            default => null,
        };

        abort_if($settingKey === null, 404);

        $path = (string) Setting::getValue($settingKey, '');

        if ($path === '') {
            return response('', 404);
        }

        $publicDisk = Storage::disk('public');

        if ($publicDisk->exists($path)) {
            return $publicDisk->response($path);
        }

        if (Storage::exists($path)) {
            return Storage::response($path);
        }

        return response('', 404);
    }

    private function variantConfig(string $variant): ?array
    {
        return match ($variant) {
            'mobile' => [
                'setting_key' => 'pwa_logo_mobile_path',
                'sizes' => '192x192',
                'default_public_path' => 'pwa/icons/default-mobile-192.png',
            ],
            'desktop' => [
                'setting_key' => 'pwa_logo_desktop_path',
                'sizes' => '512x512',
                'default_public_path' => 'pwa/icons/default-desktop-512.png',
            ],
            'retina' => [
                'setting_key' => 'pwa_logo_retina_path',
                'sizes' => '1024x1024',
                'default_public_path' => 'pwa/icons/default-retina-1024.png',
            ],
            default => null,
        };
    }

    private function resolveLogoMeta(string $variant): array
    {
        $config = $this->variantConfig($variant)
            ?? $this->variantConfig('mobile')
            ?? [
                'setting_key' => 'pwa_logo_mobile_path',
                'sizes' => '192x192',
                'default_public_path' => 'pwa/icons/default-mobile-192.png',
            ];

        $path = (string) Setting::getValue($config['setting_key'], '');

        if ($path !== '' && Storage::exists($path)) {
            $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $type = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };

            return [
                'url' => route('pwa.logo', $variant),
                'sizes' => $config['sizes'],
                'type' => $type,
            ];
        }

        return [
            'url' => route('pwa.logo', $variant),
            'sizes' => $config['sizes'],
            'type' => 'image/png',
        ];
    }
}
