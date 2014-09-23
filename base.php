<?php
/*
Base is the entry point for most pages. 
*/
?>
<?php get_template_part('templates/head'); ?>
<body <?php body_class(); ?>>


  <?php
    do_action('get_header');
    // Use Bootstrap's navbar if enabled in config.php
    if(!is_page('login')){
      get_template_part('templates/header-top-navbar');
    }
  ?>

<?php if(is_single()) { ?>
  
<?php } ?>

  <div class="container wrap" role="document">
    <div class="content row">
      <main class="main <?php echo roots_main_class(); ?>" role="main">
        <?php include roots_template_path(); ?>
      </main><!-- /.main -->
      <?php if (roots_display_sidebar()) : ?>
        <aside class="sidebar <?php echo roots_sidebar_class(); ?>" role="complementary">
          <?php 
          if(is_page('about') || is_page('contact') || is_page('pricing') || is_page('area-notification-request')) {
            include about_sidebar_path();
          } else if ( is_single() ) {
            include article_sidebar_path();
          } else {
            include roots_sidebar_path();
          }
          ?>
        </aside><!-- /.sidebar -->
      <?php endif; ?>
    </div><!-- /.content -->

  <?php if(is_front_page()){ ?>
    <?php get_template_part('templates/front', 'footer'); ?>
  <?php } ?>

  </div><!-- /.wrap -->

  <?php if(!is_front_page()){ ?>
    <?php get_template_part('templates/footer'); ?>
  <?php } ?>

</body>
</html>