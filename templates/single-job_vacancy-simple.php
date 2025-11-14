<?php
/**
 * Simple Single Job Vacancy Template
 */

get_header(); ?>

<div style="max-width: 800px; margin: 40px auto; padding: 20px; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px;">
    <?php while (have_posts()) : the_post(); ?>
    
    <article>
        <header style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #eee;">
            <h1 style="color: #2c3e50; margin-bottom: 15px;"><?php the_title(); ?></h1>
            <div style="color: #666; font-size: 14px;">
                Posted: <?php echo get_the_date(); ?>
            </div>
        </header>
        
        <div style="line-height: 1.8; color: #333;">
            <h3>Job Description</h3>
            <?php the_content(); ?>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Job Details</h3>
                <p><strong>Post Type:</strong> Job Vacancy</p>
                <p><strong>Status:</strong> <?php echo get_post_status(); ?></p>
            </div>
        </div>
        
        <footer style="margin-top: 40px; text-align: center; background: #f8f9fa; padding: 20px; border-radius: 4px;">
            <a href="<?php echo get_post_type_archive_link('job_vacancy'); ?>" style="display: inline-block; background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 0 10px;">
                ← Back to All Jobs
            </a>
            
            <button onclick="alert('Application form coming soon!')" style="background: #2271b1; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 0 10px;">
                Apply Now
            </button>
        </footer>
    </article>
    
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>