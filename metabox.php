<div class="submitbox" id="submitpost">

	<div id="minor-publishing">

		<div id="misc-publishing-actions">

			<div class="misc-pub-section misc-pub-original">

				<?php _e( 'Original', 'translate' ); ?>:

				<b>
					<?php echo translate( 'ID' ) . ' ' . get_post_meta( $id, '_original', true ); ?>
				</b>

			</div>

			<div class="misc-pub-section misc-pub-language">

				<?php echo __( 'Language', 'translate' ) . ':'; ?>

				<b class="language"><?php echo $language; ?></b>

				<a href="#post_language" class="edit-language hide-if-no-js">

					<span aria-hidden="true"><?php echo translate( 'Edit' ); ?></span>
					<span class="screen-reader-text"><?php _e( 'Edit language', 'translate' ); ?></span>

				</a>

				<div id="post-language-select" class="hide-if-js">

					<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="publish">

					<?php echo $this->make_select( $id ); ?>

					<a href="#post_language" class="save-language hide-if-no-js button"><?php echo translate( 'OK' ); ?></a>

				</div>

			</div>

			<div class="misc-pub-section misc-pub-author">

				<?php _e( 'Author', 'translate' ); ?>:

				<b><?php echo $author->data->display_name; ?></b>

			</div>

		</div>

	</div>

	<div id="major-publishing-actions">

		<div id="delete-action">

			<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $id ); ?>">
				<?php echo translate( 'Move to Trash' ); ?>
			</a>

		</div>

		<div id="publishing-action">

			<span class="spinner"></span>

			<input name="original_publish" type="hidden" id="original_publish" value="<?php echo translate( 'Update' ); ?>">
			<input name="save" type="submit" class="button button-primary button-large" id="publish" accesskey="p" value="<?php echo translate( 'Update' ); ?>">

		</div>

		<div class="clear"></div>

	</div>

</div>