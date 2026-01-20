// ============================================
// NAVIGATION FUNCTIONS
// ============================================

/**
 * Smooth scroll to a section and update active menu item
 * @param {Event} e - Click event object
 * @param {string} id - ID of the section to scroll to
 */
function scrollToSection(e, id) {
		e.preventDefault(); // Prevent default anchor behavior
		
		// Get the target section element
		const el = document.getElementById(id);
		
		// Calculate offset position (20px above the section)
		const offset = el.getBoundingClientRect().top + window.pageYOffset - 20;
		
		// Smooth scroll to the calculated position
		window.scrollTo({ top: offset, behavior: 'smooth' });
		
		// Remove 'active' class from all menu links
		document.querySelectorAll('.nav-menu a').forEach(a => a.classList.remove('active'));
		
		// Add 'active' class to clicked menu item
		e.target.classList.add('active');
		
		// Close sidebar on mobile after clicking a link
		if (window.innerWidth <= 768) document.getElementById('sidebar').classList.remove('active');
}

/**
 * Toggle mobile sidebar visibility
 */
function toggleMenu() { document.getElementById('sidebar').classList.toggle('active'); }

/**
 * Scroll to the top of the page smoothly
 */
function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }

// ============================================
// SCROLL EVENT HANDLERS
// ============================================

// Get back-to-top button element
const btn = document.getElementById('backToTop');

/**
 * Handle scroll events:
 * - Show/hide back-to-top button
 * - Update active menu item
 */
window.addEventListener('scroll', () => {
		// Show button when scrolled more than 300px from top
		btn.classList.toggle('visible', window.pageYOffset > 300);
		
		// Update active menu item as user scrolls
		updateActiveMenuItem();
});

/**
 * Update active menu item based on current scroll position
 * Highlights the menu item corresponding to the section in viewport
 */
function updateActiveMenuItem() {
		const sections = document.querySelectorAll('.content-section');
		const scroll = window.pageYOffset + 100; // Add offset for better detection
		
		sections.forEach(sec => {
				const top = sec.offsetTop, height = sec.offsetHeight, id = sec.getAttribute('id');
				
				// Check if current scroll position is within this section
				if (scroll >= top && scroll < top + height) {
						// Update active state for corresponding menu link
						document.querySelectorAll('.nav-menu a').forEach(a => {
								a.classList.toggle('active', a.getAttribute('href') === '#' + id);
						});
				}
		});
}

// ============================================
// MOBILE MENU HANDLERS
// ============================================

/**
 * Close sidebar when clicking outside of it on mobile
 */
document.addEventListener('click', e => {
		const sidebar = document.getElementById('sidebar'), toggle = document.querySelector('.menu-toggle');
		
		// Check if click is outside sidebar and toggle button on mobile
		if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('active')) {
				sidebar.classList.remove('active');
		}
});

// ============================================
// INITIALIZATION
// ============================================

/**
 * Initialize on page load
 * Set correct active menu item based on current URL hash
 */
window.addEventListener('load', updateActiveMenuItem);

// ============================================
// LIGHTBOX FUNCTIONALITY
// ============================================

/**
 * Open lightbox with an image and caption
 * @param {string} imageSrc - URL or data URI of the image to display
 * @param {string} caption - Caption text to show below the image
 */
function openLightbox(imageSrc, caption) {
		const lightbox = document.getElementById('lightbox');
		const lightboxImage = document.getElementById('lightbox-image');
		const lightboxCaption = document.getElementById('lightbox-caption');
		
		// Set image source and caption text
		lightboxImage.src = imageSrc;
		lightboxCaption.textContent = caption;
		
		// Show lightbox with active class
		lightbox.classList.add('active');
		
		// Prevent body scrolling when lightbox is open
		document.body.style.overflow = 'hidden';
}

/**
 * Close lightbox and restore body scroll
 * @param {Event} event - Click event object
 */
function closeLightbox(event) {
		// Close only if clicking on backdrop or close button
		if (event.target.id === 'lightbox' || event.target.classList.contains('lightbox-close')) {
				const lightbox = document.getElementById('lightbox');
				
				// Hide lightbox
				lightbox.classList.remove('active');
				
				// Restore body scroll
				document.body.style.overflow = '';
		}
}

// ============================================
// SCREENSHOT CLICK HANDLERS
// ============================================

/**
 * Initialize screenshot click handlers and keyboard shortcuts
 */
document.addEventListener('DOMContentLoaded', function() {
		// Add click event to all screenshot placeholders (div elements)
		document.querySelectorAll('.screenshot').forEach(screenshot => {
				screenshot.addEventListener('click', function() {
						// Extract caption from screenshot text
						const caption = this.textContent.replace('📷 ', '').trim();
						
						// Generate placeholder image (replace with actual screenshot path when ready)
						// This creates an SVG with the caption text as placeholder
						openLightbox('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1200" height="800"%3E%3Crect fill="%23f6f7f7" width="1200" height="800"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="24" fill="%23666"%3E' + encodeURIComponent(caption) + '%3C/text%3E%3C/svg%3E', caption);
				});
		});
		
		// Add click event to all screenshot images (img elements)
		document.querySelectorAll('img.screenshot').forEach(img => {
				img.addEventListener('click', function() {
						// Get image source and alt text for caption
						const imageSrc = this.src;
						const caption = this.alt || this.title || '';
						
						// Open lightbox with actual image
						openLightbox(imageSrc, caption);
				});
		});
		
		// ============================================
		// KEYBOARD SHORTCUTS
		// ============================================
		
		/**
		 * Handle keyboard events
		 * ESC key - Close lightbox if open
		 */
		document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') {
						const lightbox = document.getElementById('lightbox');
						
						// Close lightbox if it's currently open
						if (lightbox.classList.contains('active')) {
								lightbox.classList.remove('active');
								document.body.style.overflow = '';
						}
				}
		});
});