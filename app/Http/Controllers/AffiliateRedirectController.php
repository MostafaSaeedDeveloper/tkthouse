<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AffiliateRedirectController extends Controller
{
    public function __invoke(string $affiliateCode): RedirectResponse
    {
        $affiliate = User::query()
            ->where('affiliate_code', strtoupper(trim($affiliateCode)))
            ->firstOrFail();

        $targetUrl = $this->normalizeTargetUrl((string) ($affiliate->affiliate_target_url ?? ''), '/account/register');

        return redirect()->to($targetUrl.(str_contains($targetUrl, '?') ? '&' : '?').'ref='.$affiliate->affiliate_code);
    }

    private function normalizeTargetUrl(string $candidate, string $fallback): string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return $fallback;
        }

        if (str_starts_with($candidate, url('/'))) {
            $path = '/'.ltrim((string) parse_url($candidate, PHP_URL_PATH), '/');
            $query = (string) parse_url($candidate, PHP_URL_QUERY);

            return $query !== '' ? $path.'?'.$query : $path;
        }

        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }

        return '/'.ltrim($candidate, '/');
    }
}
