<?php

namespace App\Http\Middleware;

use App\Services\LegalDocumentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function __construct(private readonly LegalDocumentService $legalDocuments) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request) || ! $this->legalDocuments->needsTermsAcceptance($request->user())) {
            return $next($request);
        }

        if (! $request->isMethod('GET')) {
            return back()->with('error', 'Please accept the latest Terms and Conditions before continuing.');
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->routeIs([
            'logout',
            'legal.public.show',
            'legal.accept-terms',
        ]);
    }
}
