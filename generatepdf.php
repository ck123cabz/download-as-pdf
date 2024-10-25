<?php
require_once get_template_directory() . '/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function generate_pdf_if_requested() {
    try {
        if (!isset($_GET['generate_pdf']) || $_GET['generate_pdf'] !== 'true') {
            return;
        }

        // Check if we're on a single post/page
        if (!is_singular()) {
            wp_die('PDF generation is only available for single posts or pages.');
        }

        // Initialize dompdf with options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultMediaType', 'print');
        $options->set('chroot', get_template_directory());

        $dompdf = new Dompdf($options);

        // Get the fully rendered page content
        ob_start();
        
        // Setup post data
        global $post;
        setup_postdata($post);

        // Get the already processed Elementor content
        if (class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            $content = $elementor->frontend->get_builder_content($post->ID, true);
        }

        if (empty($content)) {
            // Fallback to regular content if needed
            $content = apply_filters('the_content', $post->post_content);
        }

        // Clean up post data
        wp_reset_postdata();
        
        // End output buffering and clean it
        ob_end_clean();

        // Enhanced CSS for better PDF output
        $css_styles = '
        <style>
            @page {
                margin: 2cm;
                size: A4;
            }
            body {
                font-family: Arial, sans-serif;
                color: #333;
                line-height: 1.6;
                font-size: 12pt;
                margin: 0;
                padding: 0;
            }
            h1 { 
                font-size: 24pt; 
                margin-bottom: 1cm;
                color: #000;
                page-break-after: avoid;
            }
            h2 { 
                font-size: 18pt; 
                margin: 1em 0;
                page-break-after: avoid;
            }
            h3 { 
                font-size: 14pt; 
                margin: 1em 0;
                page-break-after: avoid;
            }
            p { 
                margin: 0.5em 0;
                orphans: 3;
                widows: 3;
            }
            img {
                max-width: 100%;
                height: auto;
                margin: 1em 0;
                page-break-inside: avoid;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 1em 0;
                page-break-inside: avoid;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .footer {
                text-align: center;
                font-size: 9pt;
                color: #666;
                margin-top: 2cm;
                border-top: 1px solid #ddd;
                padding-top: 0.5cm;
                page-break-before: avoid;
            }
            /* Handle Elementor specific elements */
            .elementor-section {
                clear: both;
                page-break-inside: avoid;
            }
            .elementor-widget {
                page-break-inside: avoid;
            }
            /* Remove any background images and simplify colors for better PDF rendering */
            [style*="background-image"] {
                background-image: none !important;
            }
            /* Ensure lists print properly */
            ul, ol {
                padding-left: 2em;
                margin: 1em 0;
            }
            li {
                margin: 0.5em 0;
            }
        </style>';

        // Get basic page info
        $site_name = get_bloginfo('name');
        $date = current_time('F j, Y');
        $title = get_the_title();
        
        // Construct the HTML with proper DOCTYPE and meta tags
        $html_content = '
        <!DOCTYPE html>
        <html lang="' . get_bloginfo('language') . '">
        <head>
            <meta charset="' . get_bloginfo('charset') . '">
            <title>' . esc_html($title) . '</title>
            ' . $css_styles . '
        </head>
        <body>
            <h1>' . esc_html($title) . '</h1>
            ' . $content . '
            <div class="footer">
                <p>Generated on ' . esc_html($date) . ' from ' . esc_html($site_name) . '</p>
                <p>' . esc_html(get_permalink()) . '</p>
            </div>
        </body>
        </html>';

        // Set PHP settings for better performance
        ini_set('memory_limit', '512M'); // Increased memory limit
        set_time_limit(300);

        // Load HTML content into dompdf
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');

        // Render PDF (with error catching)
        try {
            $dompdf->render();
        } catch (Exception $e) {
            error_log('DOMPDF Render Error: ' . $e->getMessage());
            wp_die('Error rendering PDF. Please check error logs.');
        }

        // Generate filename
        $filename = sanitize_title($title) . '-' . date('Y-m-d') . '.pdf';
        
        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Send PDF headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output PDF
        echo $dompdf->output();
        exit();

    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        wp_die(
            'Sorry, there was an error generating the PDF. Please try again later.',
            'PDF Generation Error',
            array('response' => 500)
        );
    }
}

add_action('template_redirect', 'generate_pdf_if_requested', 20);
