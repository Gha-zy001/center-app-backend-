<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Http\Middleware\SetLocale;
use Tests\TestCase;

class LocaleHeaderTest extends TestCase
{
    private function localeFor(?string $header): string
    {
        $request = Request::create('/x');
        if ($header !== null) {
            $request->headers->set('Accept-Language', $header);
        }

        (new SetLocale)->handle($request, fn () => response('ok'));

        return App::getLocale();
    }

    public function test_accept_language_header_selects_the_locale(): void
    {
        $this->assertSame('ar', $this->localeFor('ar'));
        $this->assertSame('en', $this->localeFor('en'));
        $this->assertSame('ar', $this->localeFor('ar-SA,ar;q=0.9,en;q=0.5'));
        $this->assertSame('en', $this->localeFor('en-US,en;q=0.9'));
        $this->assertSame('en', $this->localeFor('en_US,en;q=0.5'));
    }

    public function test_unsupported_or_missing_header_falls_back_to_the_default(): void
    {
        $default = config('app.locale');

        $this->assertSame($default, $this->localeFor(null));
        $this->assertSame($default, $this->localeFor('fr-FR,fr;q=0.9'));
    }

    public function test_lang_query_parameter_is_ignored(): void
    {
        $request = Request::create('/x?lang=ar');
        $request->headers->set('Accept-Language', 'en');

        (new SetLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('en', App::getLocale());
    }
}
