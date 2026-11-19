<?php

namespace ModuHelp\Laravel\View\Components;

use Closure;
use Illuminate\View\Component;
use ModuHelp\Laravel\Facades\ModuHelp;

class Widget extends Component
{
    public function render(): Closure
    {
        return fn () => ModuHelp::render(request()) ?? '';
    }
}
