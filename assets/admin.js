var $ = jQuery;

$( document ).ready( function() {

	$( '.misc-pub-section [id^="post-"][id$="-select"]' ).each( function() {


		var type = $( this ).attr( 'id' );
		var type = type.match( 'post-(.*)-select' );

		var wrap = $( this );
		var edit = wrap.prev( '.edit-' + type[1] );

		edit.click( function() {

			$( this ).hide();
			wrap.slideToggle( 200 );

		});

		wrap.find( '.save-' + type[1] ).click( function() {

			var value = $( this ).prev( 'select' ).find( 'option:selected' ).text();

			$( 'b.' + type[1] ).html( value );

			wrap.slideToggle( 200 );
			edit.show();

		});

	});

});