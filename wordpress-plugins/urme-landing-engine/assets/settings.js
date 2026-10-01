(function ($) {
	'use strict';

	$(function () {
		var frame;
		var $chooseBtn = $('#urme_le_choose_default_image');
		var $removeBtn = $('#urme_le_remove_default_image');
		var $input = $('#urme_le_default_image_id');
		var $preview = $('.urme-le-default-image-preview');

		if (!$chooseBtn.length) {
			return;
		}

		$chooseBtn.on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: urmeLeSettings.chooseTitle,
				button: { text: urmeLeSettings.chooseButton },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var src = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
				$input.val(attachment.id);
				$preview.find('img').attr('src', src);
				$preview.show();
				$removeBtn.show();
			});

			frame.open();
		});

		$removeBtn.on('click', function (e) {
			e.preventDefault();
			$input.val('');
			$preview.hide();
			$removeBtn.hide();
		});
	});
})(jQuery);
