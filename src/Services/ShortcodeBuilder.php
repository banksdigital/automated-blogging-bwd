<?php

namespace App\Services;

use App\Helpers\Database;

/**
 * Shortcode Builder Service
 * 
 * Generates Impreza/WPBakery shortcodes from structured content
 */
class ShortcodeBuilder
{
    private int $dividerImageId;
    
    // Standard CSS for content blocks (URL encoded JSON)
    private const CONTENT_BLOCK_CSS = '%7B%22default%22%3A%7B%22background-color%22%3A%22_content_bg_alt%22%2C%22padding-left%22%3A%222.5rem%22%2C%22padding-top%22%3A%222.5rem%22%2C%22padding-bottom%22%3A%222.5rem%22%2C%22padding-right%22%3A%222.5rem%22%7D%2C%22laptops%22%3A%7B%22padding-left%22%3A%222.5rem%22%2C%22padding-top%22%3A%222.5rem%22%2C%22padding-bottom%22%3A%222.5rem%22%2C%22padding-right%22%3A%222.5rem%22%7D%2C%22tablets%22%3A%7B%22padding-left%22%3A%222.5rem%22%2C%22padding-top%22%3A%222.5rem%22%2C%22padding-bottom%22%3A%222.5rem%22%2C%22padding-right%22%3A%222.5rem%22%7D%2C%22mobiles%22%3A%7B%22padding-left%22%3A%221.5rem%22%2C%22padding-top%22%3A%222.5rem%22%2C%22padding-bottom%22%3A%222.5rem%22%2C%22padding-right%22%3A%221.5rem%22%7D%7D';
    
    // CSS for divider image
    private const DIVIDER_CSS = '%7B%22default%22%3A%7B%22max-width%22%3A%22300px%22%2C%22margin-left%22%3A%22auto%22%2C%22margin-top%22%3A%223.5rem%22%2C%22margin-bottom%22%3A%223.5rem%22%2C%22margin-right%22%3A%22auto%22%7D%7D';
    
    // CSS for carousel
    private const CAROUSEL_CSS = '%7B%22default%22%3A%7B%22margin-top%22%3A%222.5rem%22%2C%22margin-bottom%22%3A%222.5rem%22%7D%7D';
    
    // Responsive settings for carousel
    private const CAROUSEL_RESPONSIVE = '%5B%7B%22breakpoint%22%3A%22mobiles%22%2C%22breakpoint_width%22%3A%221024px%22%2C%22items%22%3A%221%22%2C%22items_offset%22%3A%2250px%22%2C%22center_item%22%3A0%2C%22autoheight%22%3A0%2C%22loop%22%3A0%2C%22autoplay%22%3A0%2C%22arrows%22%3A0%2C%22dots%22%3A1%7D%5D';

    public function __construct(array $config)
    {
        $this->dividerImageId = $config['content']['divider_image_id'] ?? 57585;
    }

    /**
     * Build complete post content from structured data
     */
    public function buildPost(array $post, array $sections): string
    {
        $output = '[vc_row columns="1"][vc_column]';
        
        // Intro text (plain, no background box)
        if (!empty($post['intro_content'])) {
            $output .= '[vc_column_text]' . $this->sanitizeContent($post['intro_content']) . '[/vc_column_text]';
        }
        
        // Sections
        foreach ($sections as $index => $section) {
            // Divider
            $output .= $this->buildDivider();
            
            // Content block with heading and text
            $output .= $this->buildContentBlock($section);
            
            // Carousel (if section has product configuration)
            if (!empty($section['carousel_brand_slug']) || !empty($section['carousel_category_slug'])) {
                $output .= $this->buildCarousel($section);
            }
        }
        
        // Outro
        if (!empty($post['outro_content'])) {
            $output .= $this->buildDivider();
            $output .= '[vc_column_text]' . $this->sanitizeContent($post['outro_content']) . '[/vc_column_text]';
        }
        
        $output .= '[/vc_column][/vc_row]';
        
        return $output;
    }

    /**
     * Build divider image element
     */
    private function buildDivider(): string
    {
        return sprintf(
            '[us_image image="%d" align="center" css="%s"]',
            $this->dividerImageId,
            self::DIVIDER_CSS
        );
    }

    /**
     * Build content block with heading, content, and CTA
     */
    private function buildContentBlock(array $section): string
    {
        $content = '';
        
        // H2 Heading
        if (!empty($section['heading'])) {
            $content .= sprintf('<h2>%s</h2>', htmlspecialchars($section['heading']));
        }
        
        // Main content (may contain multiple paragraphs)
        if (!empty($section['content'])) {
            $content .= $this->formatContent($section['content']);
        }
        
        // CTA Link
        if (!empty($section['cta_text']) && !empty($section['cta_url'])) {
            $content .= sprintf(
                "\n\n<strong><a href=\"%s\" target=\"_blank\" rel=\"noopener\">%s</a></strong>",
                htmlspecialchars($section['cta_url']),
                htmlspecialchars($section['cta_text'])
            );
        }
        
        return sprintf(
            '[vc_column_text css="%s"]%s[/vc_column_text]',
            self::CONTENT_BLOCK_CSS,
            $content
        );
    }

    /**
     * Build product carousel
     */
    private function buildCarousel(array $section): string
    {
        $attrs = [
            'items_layout' => '443',
            'items_quantity' => '10',
            'post_type' => 'product',
            'items_gap' => '0.25rem',
            'dots' => '1',
            'dots_style' => 'dash',
            'orderby' => 'post_views_counter_month',
            'exclude_items' => 'out_of_stock',
            'next_item_offset' => '50px',
            'arrows' => '1',
            'arrows_style' => '10',
            'responsive' => self::CAROUSEL_RESPONSIVE,
            'css' => self::CAROUSEL_CSS,
        ];
        
        // Add brand taxonomy filter
        if (!empty($section['carousel_brand_slug'])) {
            $attrs['taxonomy_brand'] = $section['carousel_brand_slug'];
        }
        
        // Add category taxonomy filter
        if (!empty($section['carousel_category_slug'])) {
            $attrs['taxonomy_product_cat'] = $section['carousel_category_slug'];
        }
        
        // Add any additional taxonomy filters from JSON config
        if (!empty($section['carousel_taxonomy_filter'])) {
            $filters = is_string($section['carousel_taxonomy_filter']) 
                ? json_decode($section['carousel_taxonomy_filter'], true) 
                : $section['carousel_taxonomy_filter'];
            
            if (is_array($filters)) {
                foreach ($filters as $taxonomy => $value) {
                    $attrs[$taxonomy] = $value;
                }
            }
        }
        
        // Fallback block for out-of-stock scenarios
        if (!empty($section['fallback_block_id'])) {
            $attrs['no_items_action'] = 'page_block';
            $attrs['no_items_page_block'] = $section['fallback_block_id'];
        }
        
        // Build attribute string
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= sprintf(' %s="%s"', $key, $value);
        }
        
        return sprintf('[us_carousel%s]', $attrString);
    }

    /**
     * Format content - handle paragraphs and basic formatting
     */
    private function formatContent(string $content): string
    {
        // If content doesn't have paragraph tags, add them
        if (strpos($content, '<p>') === false) {
            // Split by double newlines to create paragraphs
            $paragraphs = preg_split('/\n\s*\n/', trim($content));
            $content = implode("\n\n", array_map(function($p) {
                return trim($p);
            }, $paragraphs));
        }
        
        return $content;
    }

    /**
     * Sanitize content for safe output
     */
    private function sanitizeContent(string $content): string
    {
        // Allow basic HTML formatting
        $allowed = '<p><br><strong><b><em><i><a><h2><h3><ul><ol><li>';
        return strip_tags($content, $allowed);
    }

    /**
     * Build a simple content block without carousel (for intro/outro style sections)
     */
    public function buildSimpleBlock(string $content, bool $withBackground = false): string
    {
        $css = $withBackground ? self::CONTENT_BLOCK_CSS : '';
        
        if ($css) {
            return sprintf('[vc_column_text css="%s"]%s[/vc_column_text]', $css, $this->sanitizeContent($content));
        }
        
        return sprintf('[vc_column_text]%s[/vc_column_text]', $this->sanitizeContent($content));
    }

    /**
     * Wrap content in row/column structure
     */
    public function wrapInRow(string $content): string
    {
        return '[vc_row columns="1"][vc_column]' . $content . '[/vc_column][/vc_row]';
    }

    /**
     * Generate preview HTML from shortcodes (basic rendering for dashboard preview)
     */
    public function generatePreviewHtml(string $shortcodes): string
    {
        $html = $shortcodes;
        
        // Convert shortcodes to basic HTML for preview
        // This is a simplified preview - actual rendering happens in WordPress
        
        // Row/Column
        $html = preg_replace('/\[vc_row[^\]]*\]/', '<div class="preview-row">', $html);
        $html = str_replace('[/vc_row]', '</div>', $html);
        $html = preg_replace('/\[vc_column[^\]]*\]/', '<div class="preview-column">', $html);
        $html = str_replace('[/vc_column]', '</div>', $html);
        
        // Column text
        $html = preg_replace('/\[vc_column_text[^\]]*\]/', '<div class="preview-text">', $html);
        $html = str_replace('[/vc_column_text]', '</div>', $html);
        
        // Image
        $html = preg_replace('/\[us_image[^\]]*\]/', '<div class="preview-divider">— ✦ —</div>', $html);
        
        // Carousel
        $html = preg_replace('/\[us_carousel([^\]]*)\]/', '<div class="preview-carousel"><span class="carousel-label">Product Carousel</span><code>$1</code></div>', $html);
        
        return $html;
    }
}
