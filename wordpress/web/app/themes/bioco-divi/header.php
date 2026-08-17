<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page-container">
<?php if (!is_page_template('page-template-blank.php')) : ?>
    <header class="bioco-site-header">
        <div class="bioco-page-shell bioco-hero-nav-overlay">
            <?php echo bioco_render_primary_navigation(); ?>
        </div>
    </header>
<?php endif; ?>
<main id="bioco-main-content">
