<?php
$default_classes = [
  'animated-text-section' => 'animated-text-section',
  'animated-text-wrapper' => 'animated-text-wrapper',
  'animated-text-content' => 'animated-text-content',
  'text-desktop' => 'text-desktop',
  'text-tablet' => 'text-tablet',
  'text-mobile' => 'text-mobile',
];

$modules_file = get_template_directory() . '/assets/css/blocks/modules.json';
$classes = $default_classes;

if (file_exists($modules_file)) {
  $modules = json_decode(file_get_contents($modules_file), true);
  $classes = array_merge($default_classes, $modules['animated-text'] ?? []);
}
?>

<section class="<?php echo esc_attr( $classes['animated-text-section'] ); ?>">
  
    <div class="<?php echo esc_attr( $classes['animated-text-content'] ); ?>">

      
      <div class="<?php echo esc_attr( $classes['text-desktop'] ); ?>">
        <span>Зростай разом зі STAR WISH X.</span><br>
        <span>відкривай нові можливості</span><br>
        <span> для розвитку та досягай</span><br>
        <span>своїх цілей з нами.</span>
      </div>

      <div class="<?php echo esc_attr( $classes['text-tablet'] ); ?>">
        <span>Зростай разом зі STAR</span><br>
        <span>WISH X. відкривай нові</span><br>
        <span>можливості</span><br>
        <span>для розвитку та</span><br>
        <span>досягай</span><br>
        <span>своїх цілей з нами.</span>
      </div>

      <div class="<?php echo esc_attr( $classes['text-mobile'] ); ?>">
        <span>Зростай разом</span><br>
        <span>зі STAR WISH X.</span><br>
        <span>відкривай нові</span><br>
        <span>можливості</span><br>
        <span>для розвитку та</span><br>
        <span>досягай</span><br>
        <span>своїх цілей з</span><br>
        <span>нами.</span>
      </div>

    </div>
  
</section>
