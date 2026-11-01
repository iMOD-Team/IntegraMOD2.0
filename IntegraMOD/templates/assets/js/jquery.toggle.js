jQuery(function(){
	// hides the fastreply as soon as the DOM is ready
	// (a little sooner than page load)
	jQuery('.fastreply').hide();
	// toggles the fastreply on clicking the link
	jQuery('a.fast-reply').on('click', function() {
		jQuery('.fastreply').toggle(500);
		return false;
		});
	});

	jQuery(function(){
		jQuery("a.button").on('click', function(){
		jQuery(this).toggleClass("active").next().slideToggle();
		});

	});