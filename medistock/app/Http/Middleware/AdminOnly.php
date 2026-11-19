<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// only the Owner / Admin can open these pages
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (session('role') != "Admin") {
            return redirect()->route('dashboard')->with('flash', array("type" => "error", "text" => "Only the Owner / Admin can open that page."));
        }

        return $next($request);
    }
}
