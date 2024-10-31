jQuery(document).ready(function($) {

	//default blank <select> element

	var element = jQuery('#author_select');

	//check if on edit/add page or on search page

	if(kikxsfilterobject.single==true){

		var hidden_element = jQuery('#authordiv #post_author_override');

		var select = jQuery('#authordiv #kikxsfilterselect').val();

	}

	else{

		var hidden_element = jQuery('#author');

		var select = jQuery('form#posts-filter #kikxsfilterselect').val();

	}

	//initiate select2

	element.select2({
		placeholder: {
			id: kikxsfilterobject.default_id,
			text: kikxsfilterobject.default_login
		},
    	allowClear: true,
		minimumInputLength: kikxsfilterobject.minimum_letters,
		ajax: {
	        url: kikxsfilterobject.ajaxurl,
	        dataType: 'json',
	        type: "POST",
	        quietMillis: 1000,
	        delay: 1000,
	        data: function (params) {
	         jQuery('.select2-container--default .select2-results__option[aria-disabled=false]').html('Loading..');
		      var query = {
		        'term': params.term,
		        'page': params.page,
		        'number': kikxsfilterobject.number,
		        'action': 'kikxajaxselect', //calls wp_ajax_kikxajaxselect
				'kikxsfilterselect': select
		      }

		      return query;
		    },
	        processResults: function(data,params){
	        	jQuery('.select2-container--default .select2-results__option[aria-disabled=false]').html('Load More');
	        	params.page = params.page || 1;
	        	return {
			    results: $.map(data.result, function(obj) {
			      return {
			        id: obj.ids,
			        text: obj.user
			      };
			    }),
			    pagination: {
            		more: (params.page * kikxsfilterobject.number) < data.pagination.total,
            		text: 'Load More'
        		}
			  };
	        },
	        error: function(xhr, status, error) {
	        	var data = {user: 'No results found', id: ''}
                return {
			    results: $.map(data, function(obj) {
			      return {
			        id: obj.ids,
			        text: obj.user
			      };
			    })
			  };
            },
            cache: true
		},
		escapeMarkup: function (markup) { return markup; }
	});

	//set the value on the hidden field

	element.change(function() {

		jQuery('option',this).not(':selected').remove();

		hidden_element.prop('value',jQuery('option:selected',this).val());

	});

	//delete and set the default value on the hidden field

	element.on("select2:unselecting", function(e) {

		if(kikxsfilterobject.single==true){

			hidden_element.prop('value',kikxsfilterobject.default_id_single);

		}

		else{

			hidden_element.prop('value','');

		}

 	});

});