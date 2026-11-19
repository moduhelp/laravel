<?php

namespace ModuHelp\Laravel\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use ModuHelp\Laravel\Facades\ModuHelp;

class Widget extends Component
{
    /**
     * A string (or a Closure returning one) would be compiled as Blade source,
     * which writes the per-user token to the compiled views directory and
     * evaluates template syntax in config values. A returned Htmlable is used
     * as is. Component::resolveView() runs at render time, so the request is
     * the live one.
     */
    public function render(): HtmlString
    {
        return new HtmlString(ModuHelp::render(request()) ?? '');
    }
}
