<?php

namespace App\Http\Controllers;

use App\Services\Catalog\Catalog;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function home(): View
    {
        return view('pages.home', [
            'products' => $this->catalog->featured(),
        ]);
    }

    public function shop(): View
    {
        return view('pages.shop', [
            'products' => $this->catalog->all(),
        ]);
    }

    public function product(string $slug): View
    {
        $product = $this->catalog->findBySlug($slug);

        abort_if($product === null, 404);

        return view('pages.product', [
            'product' => $product,
            'related' => $this->catalog->all()
                ->reject(fn ($p) => $p->key === $product->key)
                ->take(3),
        ]);
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function forWhom(): View
    {
        return view('pages.for-whom');
    }

    public function freeTest(): View
    {
        return view('pages.free-test');
    }

    public function legal(string $page): View
    {
        abort_unless(in_array($page, ['privacy', 'conduct', 'refund', 'guarantee'], true), 404);

        return view('pages.legal', ['page' => $page]);
    }
}
