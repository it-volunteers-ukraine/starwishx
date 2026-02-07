<div id="searchModal" class="modal" tabindex="-1">

  <div class="modal-content modal-main">


    <form id="form-search" role="search" class="search-form" method="get" action="<?php echo home_url('/'); ?>">
      <input type="input" name="search" class="search-input"  placeholder="Напиши слово для пошуку">
      <!-- <span id="clear-form" class="clear-form-icon">&times;</span> -->
       <div id="clear-form" class="form-clear-btn">
         <svg  class="form-clear-icon">
           <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-close"></use>
         </svg>
       </div>
       <!-- <div id="speech" class="form-clear-btn speech">
         <svg  class="form-clear-icon">
           <use xlink:href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-sound"></use>
         </svg>
       </div> -->
      <button type="submit" class="search-submit-bth">
        <svg class="search-submit-icon">
          <use xlink:href="<?php echo esc_url(get_template_directory_uri() . '/assets/img/sprites.svg#icon-find'); ?>"></use>
        </svg>
      </button>
    </form>

  </div>

</div>