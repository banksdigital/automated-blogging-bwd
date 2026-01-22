<?php

namespace App\Controllers;

use App\Helpers\Database;

class TaxonomyController
{
    public function __construct(array $config) {}

    public function categories(): void
    {
        $categories = Database::query("SELECT * FROM wp_categories ORDER BY name");
        echo json_encode(['success' => true, 'data' => $categories]);
    }

    public function authors(): void
    {
        $authors = Database::query("SELECT * FROM wp_authors ORDER BY name");
        echo json_encode(['success' => true, 'data' => $authors]);
    }

    public function blocks(): void
    {
        $blocks = Database::query("SELECT * FROM wp_page_blocks ORDER BY title");
        echo json_encode(['success' => true, 'data' => $blocks]);
    }
}
