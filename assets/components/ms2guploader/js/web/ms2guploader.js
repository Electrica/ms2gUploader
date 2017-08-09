typeof $.fn.sortable == 'function' || document.write('<script src="' + ms2guploaderConfig.vendorUrl + 'jquery-ui-sortable/jquery-ui-1.10.4.sortable.min.js"><\/script>');
typeof $.fn.ajaxForm == 'function' || document.write('<script src="' + ms2guploaderConfig.vendorUrl + 'jquery-form/jquery.form.js"><\/script>');
typeof $.fn.jGrowl == 'function' || document.write('<script src="' + ms2guploaderConfig.vendorUrl + 'jgrowl/jquery.jgrowl.min.js"><\/script>');
typeof $.fn.Plupload == 'function' || document.write('<script src="' + ms2guploaderConfig.vendorUrl + 'plupload/js/plupload.full.min.js"><\/script>');
typeof $.fn.Plupload == 'function' || document.write('<script src="' + ms2guploaderConfig.vendorUrl + 'plupload/js/i18n/ru.js"><\/script>');

var ms2guploader = {
    config : {
      actionUrl : ms2guploaderConfig.actionUrl,
      assetsUrl : ms2guploaderConfig.assetsUrl,
      vendorUrl : ms2guploaderConfig.vendorUrl,
      locale: ms2guploaderConfig.cultureKey
    },

    selectors: {
      form: '#ms2guploader',
	  file: '.ms2gu-file',
      fileDelete: '.ms2gu-file-delete',
      uploader: {
	        browse_button: 'ms2gu-files-select',
	        // upload_button: document.getElementById('ms2gu-files-upload'),
	        container: 'ms2gu-files-container',
	        filelist: 'ms2gu-files-list',
	        progress: 'ms2gu-files-progress',
	        progress_bar: 'ms2gu-files-progress-bar',
	        progress_count: 'ms2gu-files-progress-count',
	        progress_percent: 'ms2gu-files-progress-percent',
	        drop_element: 'ms2gu-files-list'
      }
    },

	sort: function() {
		var rank = {};
		$('#' + ms2guploader.selectors.uploader.filelist).find(ms2guploader.selectors.file).each(function(i){
			rank[i] = $(this).data('id');
		});
		var data = {
			action: 'gallery/sort',
			rank: rank
		};
		$.post(ms2guploader.config.actionUrl, data, function(response) {
			if (!response.success) {
				ms2guploader.message.error(response.message);
			}
		}, 'json');
	},

	limit: function(new_count) {


	},

    initialize: function() {
		var form = $(ms2guploader.selectors.form);

	  // Uploader
	  ms2guploader.Uploader = new plupload.Uploader({
        runtimes: 'html5,flash,silverlight,html4',
        browse_button: ms2guploader.selectors.uploader.browse_button,
        container: ms2guploader.selectors.uploader.container,
        filelist: ms2guploader.selectors.uploader.filelist,
        progress_percent: ms2guploader.selectors.uploader.progress_percent,
        drop_element: ms2guploader.selectors.uploader.drop_element,
        form: form,
        multipart_params: {
          action: $('#' + this.container).data('action') || 'gallery/upload',
          tid: $('input[name="tid"]').val(),
		  ctx: ms2guploaderConfig.ctx,
		  thumbsize: ms2guploaderConfig.thumbsize,
		  source: ms2guploaderConfig.source,
		  tpl: ms2guploaderConfig.tpl,
        },
        url: ms2guploader.config.actionUrl,
        flash_swf_url: ms2guploader.config.vendorUrl + 'lib/plupload/js/Moxie.swf',
	    silverlight_xap_url: ms2guploader.config.vendorUrl + 'lib/plupload/js/Moxie.xap',
        init: {
          Init: function (up) {
		  	if (this.runtime == 'html5') {
              var element = $(this.settings.drop_element);
              element.addClass('droppable');
              element.on('dragover', function () {
                if (!element.hasClass('dragover')) {
                  element.addClass('dragover');
                }
              });
              element.on('dragleave drop', function () {
                element.removeClass('dragover');
              });
            }
          },
          PostInit: function (up) {},
          FilesAdded: function (up, files) {
		  	var count = $('#' + ms2guploader.selectors.uploader.filelist).find(ms2guploader.selectors.file).length - 1;
		  	var limit = ms2guploaderConfig.uploadLimit;
		  	if((up.files.length  + count) >= limit) {
				up.splice(limit - count);
				$('#' + ms2guploader.selectors.uploader.browse_button).addClass('disabled');
			}
			up.start();
		  },
          UploadProgress: function (up, file) {
            $('#' + ms2guploader.selectors.uploader.browse_button).addClass('uploading');
			$('#' + up.settings.progress_percent).text(up.total.percent + '%');
          },
          FileUploaded: function (up, file, response) {
		  	console.log('uploaded');

            response = $.parseJSON(response.response);
			//console.log(response);
            if (response.success) {
              $('#' + up.settings.filelist + ' .note').hide();
              // Successfull action
              var files = $('#' + up.settings.filelist);
              files.append(response.data.html);
            } else {
              ms2guploader.message.error(response.message);
            }
          },
          UploadComplete: function (up, file, response) {
		  	$('#' + ms2guploader.selectors.uploader.browse_button).removeClass('uploading');

            up.total.reset();
            up.splice();
            this.settings.form.find('[type="submit"]').attr('disabled', false);
          },
          Error: function (up, err) {
		  	 ms2guploader.message.error(err.message);
		  	 console.log(err);
          }
        }
      });
      ms2guploader.Uploader.init();

      // sort
      $('#' + ms2guploader.selectors.uploader.filelist).sortable({
          items: ms2guploader.selectors.file+':not(.static)',
          update: function( event, ui ) {
              var rank = {};
              $('#' + ms2guploader.selectors.uploader.filelist).find(ms2guploader.selectors.file).each(function(i){
                  rank[i] = $(this).data('id');
              });
              var data = {
                  action: 'gallery/sort',
                  rank: rank
              };
              $.post(ms2guploader.config.actionUrl, data, function(response) {
                  if (!response.success) {
                      ms2guploader.message.error(response.message);
                  }
              }, 'json');
          }
      });

      // delete
      $(document).on('click', ms2guploader.selectors.fileDelete, function (e) {
        e.preventDefault();
		var _confirm = confirm('Удалить изображение?');
		if(!_confirm) return;

		var wrapper = $(this).closest(ms2guploader.selectors.file);
		var id = wrapper.data('id');

        $.post(ms2guploader.config.actionUrl, {
          action: 'gallery/delete',
          id: id,
        }, function (response, textStatus, jqXHR) {
          if (response.success) {
            wrapper.remove();
			var count = $('#' + ms2guploader.selectors.uploader.filelist).find(ms2guploader.selectors.file).length - 1;
		  	var limit = ms2guploaderConfig.uploadLimit;
		  	if(count < limit) {
		  		$('#' + ms2guploader.selectors.uploader.browse_button).removeClass('disabled');
		  	}
			//ms2guploader.sort();
          } else {
            ms2guploader.message.error(response.message);
          }
        }, 'json');
      });

    },

	message: {
		success: function (message) {
			if (!message) return;
			$.jGrowl(message, { theme: 'ms2gus-message-success' });
		},
		error: function (message) {
			if (!message) return;
			$.jGrowl(message, { theme: 'ms2gus-message-error' });
		},
		info: function (message) {
			if (!message) return;
			$.jGrowl(message, {theme: 'ms2gus-message-info'});
		},
		close: function () {
			$.jGrowl('close');
		}
	}
};

$(document).ready(ms2guploader.initialize);