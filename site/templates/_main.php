<?php namespace ProcessWire;

// Main output file - defines wireframe structure for all pages
// Template variations can override CSS by setting $page->css_variant field
// When the Markup Regions feature is used, template files can prepend, append,
// replace or delete any element defined here that has an "id" attribute. 
// https://processwire.com/docs/front-end/output/markup-regions/
	
/** @var Page $page */
/** @var Pages $pages */
/** @var Config $config */
	
$home = $pages->get('/');
// Get CSS variant - defaults to 'wireframe', can be overridden by template variations
$cssVariant = $page->css_variant ? $page->css_variant : 'wireframe';

// SEO data
$seoTitle = ($page->hasField('seo_title') && $page->seo_title) ? $page->seo_title : $page->title;
$seoDescription = ($page->hasField('seo_description') && $page->seo_description) ? $page->seo_description : '';
$canonicalUrl = ($page->hasField('canonical_url') && $page->canonical_url) ? $page->canonical_url : $page->httpUrl;
$robotsNoindex = $page->hasField('robots_noindex') && $page->robots_noindex;
$robotsNofollow = $page->hasField('robots_nofollow') && $page->robots_nofollow;

// OG Image: use og_image field, fallback to hero_image
$ogImageUrl = null;
if ($page->hasField('og_image') && $page->og_image) {
    $ogImageUrl = $page->og_image->httpUrl;
} elseif ($page->hasField('hero_image') && $page->hero_image) {
    $ogImageUrl = $page->hero_image->httpUrl;
}

?><!DOCTYPE html>
<html lang="de">
	<head id="html-head">
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title id="html-title"><?php echo htmlspecialchars($seoTitle); ?></title>
		
		<?php // Meta Description ?>
		<?php if($seoDescription): ?>
		<meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>" />
		<?php endif; ?>
		
		<?php // Canonical URL ?>
		<link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>" />
		
		<?php // Robots meta tag ?>
		<?php if($robotsNoindex || $robotsNofollow): ?>
		<meta name="robots" content="<?php echo $robotsNoindex ? 'noindex' : 'index'; ?>, <?php echo $robotsNofollow ? 'nofollow' : 'follow'; ?>" />
		<?php endif; ?>
		
		<?php // Open Graph tags ?>
		<meta property="og:title" content="<?php echo htmlspecialchars($seoTitle); ?>" />
		<?php if($seoDescription): ?>
		<meta property="og:description" content="<?php echo htmlspecialchars($seoDescription); ?>" />
		<?php endif; ?>
		<meta property="og:url" content="<?php echo htmlspecialchars($page->httpUrl); ?>" />
		<meta property="og:type" content="website" />
		<meta property="og:locale" content="de_CH" />
		<meta property="og:site_name" content="biocò" />
		<?php if($ogImageUrl): ?>
		<meta property="og:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>" />
		<?php endif; ?>
		
		<?php // Twitter Card tags ?>
		<meta name="twitter:card" content="summary_large_image" />
		<meta name="twitter:title" content="<?php echo htmlspecialchars($seoTitle); ?>" />
		<?php if($seoDescription): ?>
		<meta name="twitter:description" content="<?php echo htmlspecialchars($seoDescription); ?>" />
		<?php endif; ?>
		<?php if($ogImageUrl): ?>
		<meta name="twitter:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>" />
		<?php endif; ?>
		
		<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates; ?>styles/<?php echo $cssVariant; ?>.css" />
	</head>
	<body id="html-body">
		
		<!-- Header Section -->
		<header id="header" class="wireframe-box">
			<div class="wireframe-content">
				<div id="header-logo" class="wireframe-placeholder">
					<?php if($page->logo_image): ?>
						<img src="<?php echo $page->logo_image->url; ?>" alt="<?php echo $page->logo_image->description; ?>" />
					<?php else: ?>
						<span class="wireframe-label">Logo</span>
					<?php endif; ?>
				</div>
				<nav id="header-nav" class="wireframe-box">
					<?php if($home->children->count()): ?>
						<ul>
							<?php foreach($home->children as $child): ?>
								<li><a href="<?php echo $child->url; ?>"><?php echo $child->title; ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php else: ?>
						<span class="wireframe-label">Navigation</span>
					<?php endif; ?>
				</nav>
			</div>
		</header>

		<!-- Hero/Banner Section -->
		<section id="hero" class="wireframe-box">
			<div class="wireframe-content">
				<?php if($page->hero_image): ?>
					<div id="hero-image" class="wireframe-image">
						<img src="<?php echo $page->hero_image->url; ?>" alt="<?php echo $page->hero_image->description; ?>" />
					</div>
				<?php else: ?>
					<div id="hero-image" class="wireframe-placeholder">
						<span class="wireframe-label">Hero Image</span>
					</div>
				<?php endif; ?>
				<div id="hero-text" class="wireframe-box">
					<h1 id="hero-title"><?php echo $page->title; ?></h1>
					<?php if($page->hero_subtitle): ?>
						<p id="hero-subtitle"><?php echo $page->hero_subtitle; ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<!-- Main Content Section -->
		<main id="main-content" class="wireframe-box">
			<div class="wireframe-content">
				<div id="content" class="wireframe-box">
					<!-- Content will be replaced by template files using Markup Regions -->
					Default content
				</div>
				
				<!-- Sidebar (if needed) -->
				<?php if($page->sidebar_content): ?>
				<aside id="sidebar" class="wireframe-box">
					<div class="wireframe-content">
						<?php echo $page->sidebar_content; ?>
					</div>
				</aside>
				<?php endif; ?>
			</div>
		</main>

		<!-- Image Gallery Section (if images exist) -->
		<?php if($page->gallery_images && $page->gallery_images->count()): ?>
		<section id="gallery" class="wireframe-box">
			<div class="wireframe-content">
				<h2 class="wireframe-label">Image Gallery</h2>
				<div class="wireframe-gallery">
					<?php foreach($page->gallery_images as $img): ?>
						<div class="wireframe-image">
							<img src="<?php echo $img->url; ?>" alt="<?php echo $img->description; ?>" />
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<!-- Footer Section -->
		<footer id="footer" class="wireframe-box">
			<div class="wireframe-content">
				<div id="footer-content" class="wireframe-box">
					<?php if($page->footer_content): ?>
						<?php echo $page->footer_content; ?>
					<?php else: ?>
						<span class="wireframe-label">Footer Content</span>
					<?php endif; ?>
				</div>
			</div>
		</footer>

		<!-- Analytics Script (Matomo cookieless) -->
		<?php if($modules->isInstalled('MatomoTracker')): ?>
			<?php $matomoTracker = $modules->get('MatomoTracker'); ?>
			<script id="matomo-tracker">
				<?php echo $matomoTracker->getTrackingScript(); ?>
			</script>
		<?php endif; ?>

	</body>
</html>