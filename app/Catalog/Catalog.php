<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Rtdb\Contracts\RtdbClient;

/**
 * Reads the menu catalog — `products` and `categories` — from RTDB.
 *
 * Admin screens read fresh so an edit shows immediately; the customer menu
 * (#22) serves the same shapes from the coalesced `catalog` cache, which this
 * ticket's writes evict. A product node that cannot be parsed is skipped rather
 * than rendered as a half-row.
 */
final readonly class Catalog
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Every product, keyed by id.
     *
     * @return array<string, Product>
     */
    public function products(): array
    {
        return $this->productsFromNode($this->client->get('products'));
    }

    /**
     * The menu set: products the admin has not hidden. Menu items carry no
     * stock, so this is the whole 86 board.
     *
     * @return array<string, Product>
     */
    public function menu(): array
    {
        $menu = [];

        foreach ($this->products() as $id => $product) {
            if (! $product->hidden) {
                $menu[$id] = $product;
            }
        }

        return $menu;
    }

    /**
     * Categories in the admin's order (then by name), keyed by id.
     *
     * @return array<string, Category>
     */
    public function categories(): array
    {
        $node = $this->client->get('categories');

        if (! is_array($node)) {
            return [];
        }

        $categories = [];

        foreach ($node as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }

            $category = Category::fromNode($id, $row);

            if ($category !== null) {
                $categories[$id] = $category;
            }
        }

        uasort($categories, static function (Category $a, Category $b): int {
            return [$a->order, $a->name] <=> [$b->order, $b->name];
        });

        return $categories;
    }

    public function product(string $id): ?Product
    {
        $node = $this->client->get("products/{$id}");

        return is_array($node) ? Product::fromNode($id, $node) : null;
    }

    public function category(string $id): ?Category
    {
        $node = $this->client->get("categories/{$id}");

        return is_array($node) ? Category::fromNode($id, $node) : null;
    }

    /**
     * @param  mixed  $node
     * @return array<string, Product>
     */
    private function productsFromNode(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $products = [];

        foreach ($node as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }

            $product = Product::fromNode($id, $row);

            if ($product !== null) {
                $products[$id] = $product;
            }
        }

        return $products;
    }
}
