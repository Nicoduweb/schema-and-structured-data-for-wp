<?php
/**
     * We are here fetching all schema and its settings from backup files
     * note: Transaction is applied on this function, if any error occure all the data will be rollbacked
     * @global type $wpdb
     * @return boolean
     */
    add_action('admin_init', 'saswp_import_all_settings_and_schema',9);
    function saswp_import_all_settings_and_schema(){
        
        $url = get_option('saswp-file-upload_url');        
        global $wpdb;
        $result = '';
        $errorDesc   = array();
         
        if($url){
            
        $json_data       = file_get_contents($url);
        $json_array      = json_decode($json_data, true);       
        $all_schema_post = $json_array['posts'];
         
        $sd_data     = $json_array['sd_data'];                
        $schema_post = array();                     
               
        if($all_schema_post){
            // begin transaction
            $wpdb->query('START TRANSACTION');
            
            foreach($all_schema_post as $schema_post){  
                
                $post_id = wp_insert_post($schema_post['post']);
                $result  = $post_id;
                $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");   
                                
                if ( isset( $schema_post['schema_type'] ) ){
                        update_post_meta( $post_id, 'schema_type', esc_attr( $schema_post['schema_type'] ) );
                }
                                
                if ( isset( $schema_post['saswp_business_type'] ) ){
                        update_post_meta( $post_id, 'saswp_business_type', $schema_post['saswp_business_type']  );
                }
                
                if ( isset( $schema_post['saswp_business_name'] ) ){
                        update_post_meta( $post_id, 'saswp_business_name', $schema_post['saswp_business_name']  );
                }
                
                if ( isset( $schema_post['saswp_local_business_details'] ) ){
                        update_post_meta( $post_id, 'saswp_local_business_details', $schema_post['saswp_local_business_details']  );
                }
                if ( isset( $schema_post['data_group_array'] ) ){
                        update_post_meta( $post_id, 'data_group_array', $schema_post['data_group_array']  );
                }                                                                                                     
                if(is_wp_error($result)){
                    $errorDesc[] = $result->get_error_message();
                }
                }          
                }                
             update_option('sd_data', $sd_data); 
             update_option('saswp-file-upload_url','');
            }                                    
            if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);              
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }            
        
                             
    }   
/**
     * We are here exporting all schema types and its settings as a backup file     
     * @global type $wpdb
     * @return boolean
     */
    function saswp_export_all_settings_and_schema(){   
        
        $export_data     = array();
        $export_data_all = array();
        $schema_post     = array();       
        $user_id         = get_current_user_id();
        
        $all_schema_post = get_posts(
                
                    array(
                            'post_type' 	 => 'saswp',                                                                                   
                            'posts_per_page'     => -1,   
                            'post_status'        => 'any',
                    )
                
                 ); 
        
        if($all_schema_post){     
            
            foreach($all_schema_post as $schema){    
                
                $schema_post = array( 
                    
                    'post_author'           => $user_id,
                    'post_date'             => $schema->post_date,
                    'post_date_gmt'         => $schema->post_date_gmt,
                    'post_content'          => $schema->post_content,
                    'post_title'            => $schema->post_title,
                    'post_excerpt'          => $schema->post_excerpt,
                    'post_status'           => $schema->post_status,
                    'comment_status'        => $schema->comment_status,
                    'ping_status'           => $schema->ping_status,
                    'post_password'         => $schema->post_password,
                    'post_name'             => $schema->post_name,
                    'to_ping'               => $schema->to_ping,
                    'pinged'                => $schema->pinged,
                    'post_modified'         => $schema->post_modified,
                    'post_modified_gmt'     => $schema->post_modified_gmt,
                    'post_content_filtered' => $schema->post_content_filtered,
                    'post_parent'           => $schema->post_parent,                                        
                    'menu_order'            => $schema->menu_order,
                    'post_type'             => 'saswp',
                    'post_mime_type'        => $schema->post_mime_type,
                    'comment_count'         => $schema->comment_count,
                    'filter'                => $schema->filter, 
                    
                ); 
                
                $export_data[$schema->ID]['post'] = $schema_post;    
                
                $post_meta                 = get_post_meta($schema->ID, $key='', true );
                
                $schema_type               =  saswp_remove_warnings($post_meta, 'schema_type', 'saswp_array');
                $local_business_type       =  saswp_remove_warnings($post_meta, 'saswp_business_type', 'saswp_array');
                $local_business_sub_type   =  saswp_remove_warnings($post_meta, 'saswp_business_name', 'saswp_array');
                
                
                $data_group_array          = get_post_meta($schema->ID, $key='data_group_array', true );
                $local_business_details    = get_post_meta($schema->ID, $key='saswp_local_business_details', true );
                
                
                $export_data[$schema->ID]['schema_type']                  = $schema_type; 
                $export_data[$schema->ID]['saswp_business_type']          = $local_business_type; 
                $export_data[$schema->ID]['saswp_business_name']          = $local_business_sub_type; 
                $export_data[$schema->ID]['data_group_array']             = $data_group_array; 
                $export_data[$schema->ID]['saswp_local_business_details'] = $local_business_details;                 
              }       
                
                $get_sd_data = get_option('sd_data');                
                $export_data_all['posts'] =$export_data;
                $export_data_all['sd_data'] =$get_sd_data;
                header('Content-type: application/json');
                header('Content-disposition: attachment; filename=structuredatabackup.json');
                echo json_encode($export_data_all);   
                
        }else{
            
                header('Content-type: application/json');
                header('Content-disposition: attachment; filename=structuredatabackup.json');
                echo json_encode(array('message'=> 'Data is not available'));
                
        }                          
        wp_die();
    }
    add_action( 'wp_ajax_saswp_export_all_settings_and_schema', 'saswp_export_all_settings_and_schema');
/**
     * We are here fetching all schema and its settings from schema plugin
     * note: Transaction is applied on this function, if any error occure all the data will be rollbacked
     * @global type $wpdb
     * @return boolean
     */
    function saswp_import_schema_plugin_data(){           
                                                    
        $schema_post = array();
        $errorDesc = array();
        global $wpdb;
        $user_id     = get_current_user_id();
        
        $all_schema_post = get_posts(
                    array(
                            'post_type' 	 => 'schema',                                                                                   
                            'posts_per_page' => -1,   
                            'post_status' => 'any',
                    )
                 );         
        
        if($all_schema_post){
            // begin transaction
            $wpdb->query('START TRANSACTION');
            
            foreach($all_schema_post as $schema){    
                
                $schema_post = array(
                    
                    'post_author'           => $user_id,
                    'post_date'             => $schema->post_date,
                    'post_date_gmt'         => $schema->post_date_gmt,
                    'post_content'          => $schema->post_content,
                    'post_title'            => $schema->post_title. ' (Migrated from Schema plugin)',
                    'post_excerpt'          => $schema->post_excerpt,
                    'post_status'           => $schema->post_status,
                    'comment_status'        => $schema->comment_status,
                    'ping_status'           => $schema->ping_status,
                    'post_password'         => $schema->post_password,
                    'post_name'             => $schema->post_name,
                    'to_ping'               => $schema->to_ping,
                    'pinged'                => $schema->pinged,
                    'post_modified'         => $schema->post_modified,
                    'post_modified_gmt'     => $schema->post_modified_gmt,
                    'post_content_filtered' => $schema->post_content_filtered,
                    'post_parent'           => $schema->post_parent,                                        
                    'menu_order'            => $schema->menu_order,
                    'post_type'             => 'saswp',
                    'post_mime_type'        => $schema->post_mime_type,
                    'comment_count'         => $schema->comment_count,
                    'filter'                => $schema->filter, 
                    
                );                                      
                $post_id = wp_insert_post($schema_post);
                $result  = $post_id;
                $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");   
                
                $schema_post_meta       = get_post_meta($schema->ID, $key='', true ); 
                $schema_post_types      = get_post_meta($schema->ID, $key='_schema_post_types', true );                  
                $schema_post_meta_box   = get_post_meta($schema->ID, $key='_schema_post_meta_box', true );
                
                $data_group_array = array();
                
                if($schema_post_types){
                                        
                    $i=0;
                    foreach ($schema_post_types as $post_type){
                       
                       $data_group_array['group-'.$i] =array(
                          'data_array' => array(
                            array(
                            'key_1' => 'post_type',
                            'key_2' => 'equal',
                            'key_3' => $post_type,
                            )
                          )               
                         );                                               
                    $i++;  
                    
                    }                                        
                }                                
                $schema_type         ='';
                $schema_article_type ='';                                                
                
                if(isset($schema_post_meta['_schema_type'])){
                  $schema_type = $schema_post_meta['_schema_type'];  
                }
                if(isset($schema_post_meta['_schema_article_type'])){
                  $schema_article_type = $schema_post_meta['_schema_article_type'][0];  
                }                      
                $saswp_meta_key = array(
                    'schema_type' => $schema_article_type,
                    'data_group_array'=>$data_group_array,
                    'imported_from' => 'schema'
                );
                
                foreach ($saswp_meta_key as $key => $val){                     
                    update_post_meta($post_id, $key, $val);  
                }                                                        
                if(is_wp_error($result)){
                    $errorDesc[] = $result->get_error_message();
                }
              }          
                            
              //Importing settings starts here
                            
                $schema_plugin_options = get_option('schema_wp_settings');                                      
                $custom_logo_id        = get_theme_mod( 'custom_logo' );
                $logo                  = wp_get_attachment_image_src( $custom_logo_id , 'full' );
                                
                $saswp_plugin_options = array(                    
                    'sd_logo'   => array(
                                        'url'           =>$schema_plugin_options['logo'],  
                                        'id'            =>$custom_logo_id,
                                        'height'        =>'600',
                                        'width'         =>'60',
                                        'thumbnail'     =>$schema_plugin_options['logo']        
                            ),                                                                                                                                                             
                    'saswp_kb_contact_1'       => 0,                                                                            
                    //AMP Block           
                    'saswp-for-amp'            => 1, 
                    'saswp-for-wordpress'      => 1,      
                    'saswp-logo-width'         => '60',
                    'saswp-logo-height'        => '60',                    
                    'sd_initial_wizard_status' => 1,
                                        
                );                
                if(isset($schema_plugin_options['facebook'])){
                  $saswp_plugin_options['sd_facebook'] =  $schema_plugin_options['facebook']; 
                  $saswp_plugin_options['saswp-facebook-enable'] =  1; 
                }
                if(isset($schema_plugin_options['twitter'])){
                  $saswp_plugin_options['sd_twitter'] =  $schema_plugin_options['twitter']; 
                  $saswp_plugin_options['saswp-twitter-enable'] =  1;
                }
                if(isset($schema_plugin_options['google'])){
                  $saswp_plugin_options['sd_google_plus'] =  $schema_plugin_options['google']; 
                  $saswp_plugin_options['saswp-google-plus-enable'] =  1;
                }
                if(isset($schema_plugin_options['instagram'])){
                  $saswp_plugin_options['sd_instagram'] =  $schema_plugin_options['instagram']; 
                  $saswp_plugin_options['saswp-instagram-enable'] =  1;
                }
                if(isset($schema_plugin_options['youtube'])){
                  $saswp_plugin_options['sd_youtube'] =  $schema_plugin_options['youtube']; 
                  $saswp_plugin_options['saswp-youtube-enable'] =  1;
                }
                if(isset($schema_plugin_options['linkedin'])){
                  $saswp_plugin_options['sd_linkedin'] =  $schema_plugin_options['linkedin']; 
                  $saswp_plugin_options['saswp-linkedin-enable'] =  1;
                }
                if(isset($schema_plugin_options['pinterest'])){
                  $saswp_plugin_options['sd_pinterest'] =  $schema_plugin_options['pinterest']; 
                  $saswp_plugin_options['saswp-pinterest-enable'] =  1;
                }
                if(isset($schema_plugin_options['soundcloud'])){
                  $saswp_plugin_options['sd_soundcloud'] =  $schema_plugin_options['soundcloud']; 
                  $saswp_plugin_options['saswp-soundcloud-enable'] =  1;
                }
                if(isset($schema_plugin_options['tumblr'])){
                  $saswp_plugin_options['sd_tumblr'] =  $schema_plugin_options['tumblr']; 
                  $saswp_plugin_options['saswp-tumblr-enable'] =  1;
                }                
                if(isset($schema_plugin_options['organization_or_person'])){
                                                           
                  $saswp_plugin_options['saswp_kb_type'] = ucfirst($schema_plugin_options['organization_or_person']);  
                  $saswp_plugin_options['sd_name'] = $schema_plugin_options['name'];
                  $saswp_plugin_options['sd-person-name'] = $schema_plugin_options['name'];
                }                
                if(isset($schema_plugin_options['about_page'])){
                  $saswp_plugin_options['sd_about_page'] = $schema_plugin_options['about_page'];  
                }
                if(isset($schema_plugin_options['contact_page'])){
                  $saswp_plugin_options['sd_contact_page'] = $schema_plugin_options['contact_page'];  
                }
                if(isset($schema_plugin_options['site_name'])){
                   
                }
                if(isset($schema_plugin_options['site_alternate_name'])){
                  $saswp_plugin_options['sd_alt_name'] = $schema_plugin_options['site_alternate_name'];  
                }
                if(isset($schema_plugin_options['url'])){
                  $saswp_plugin_options['sd_url'] = $schema_plugin_options['url'];  
                  $saswp_plugin_options['sd-person-url'] = $schema_plugin_options['url'];  
                }
                if(isset($schema_plugin_options['name'])){
                  $saswp_plugin_options['sd-person-name'] = $schema_plugin_options['name'];  
                }
                if(isset($schema_plugin_options['corporate_contacts_telephone'])){
                  $saswp_plugin_options['saswp_kb_telephone'] = $schema_plugin_options['corporate_contacts_telephone'];  
                }
                if(isset($schema_plugin_options['corporate_contacts_contact_type'])){
                  $saswp_plugin_options['saswp_contact_type'] = $schema_plugin_options['corporate_contacts_contact_type'];  
                }                
                if(isset($schema_plugin_options['breadcrumbs_enable'])){
                  $saswp_plugin_options['saswp_breadcrumb_schema'] = $schema_plugin_options['breadcrumbs_enable'];  
                }                
                update_option('sd_data', $saswp_plugin_options);
                //Importing settings ends here
              
            if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc); 
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }            
        }
                             
    }
    
    function saswp_import_seo_pressor_plugin_data(){
         
        global $wpdb;
        $social_fields = array();
        $opening_hours = '';
        $settings = WPPostsRateKeys_Settings::get_options();
        
        if(isset($settings['seop_home_social'])){
            
            foreach($settings['seop_home_social'] as $social){
               
                switch ($social['social_type']) {
                    
                    case 'Facebook':
                        
                        $social_fields['saswp-facebook-enable'] = 1;
                        $social_fields['sd_facebook'] = $social['social'];
                        
                        break;
                    case 'Twitter':
                        
                        $social_fields['saswp-twitter-enable'] = 1;
                        $social_fields['sd_twitter'] = $social['social'];
                        
                        break;
                    case 'Google+':
                        $social_fields['saswp-google-plus-enable'] = 1;
                        $social_fields['sd_google_plus'] = $social['social'];
                        break;
                    case 'Instagram':
                        $social_fields['saswp-instagram-enable'] = 1;
                        $social_fields['sd_instagram'] = $social['social'];
                        break;
                    case 'YouTube':
                        $social_fields['saswp-youtube-enable'] = 1;
                        $social_fields['sd_youtube'] = $social['social'];
                        break;
                    case 'LinkedIn':
                        $social_fields['saswp-linkedin-enable'] = 1;
                        $social_fields['sd_linkedin'] = $social['social'];
                        break;                    
                    case 'Pinterest':
                        $social_fields['saswp-pinterest-enable'] = 1;
                        $social_fields['sd_pinterest'] = $social['social'];
                        break;
                    case 'SoundCloud':
                        $social_fields['saswp-soundcloud-enable'] = 1;
                        $social_fields['sd_soundcloud'] = $social['social'];
                        break;
                    case 'Tumblr':
                        $social_fields['saswp-tumblr-enable'] = 1;
                        $social_fields['sd_tumblr'] = $social['social'];
                        break;

                    default:
                        break;
                }
                                                
            }         
        }
       
        if(isset($settings['seop_operating_hour'])){
            
           $hours = $settings['seop_operating_hour'];
           
           if(isset($hours['Mo'])){
             $opening_hours .='Mo-Mo'.' '.$hours['Mo']['from'].'-'.$hours['Mo']['to'].' '; 
           }
           if(isset($hours['Tu'])){
              $opening_hours .='Tu-Tu'.' '.$hours['Tu']['from'].'-'.$hours['Tu']['to'].' '; 
           }
           if(isset($hours['We'])){
              $opening_hours .='We-We'.' '.$hours['We']['from'].'-'.$hours['We']['to'].' '; 
           }
           if(isset($hours['Th'])){
              $opening_hours .='Th-Th'.' '.$hours['Th']['from'].'-'.$hours['Th']['to'].' '; 
           }
           if(isset($hours['Fr'])){
             $opening_hours .='Fr-Fr'.' '.$hours['Fr']['from'].'-'.$hours['Fr']['to'].' ';  
           }
           if(isset($hours['Sa'])){
             $opening_hours .='Sa-Sa'.' '.$hours['Sa']['from'].'-'.$hours['Sa']['to'].' '; 
           }
           if(isset($hours['Su'])){
             $opening_hours .='Su-Su'.' '.$hours['Su']['from'].'-'.$hours['Su']['to'];
           }
        } 
        
        
         if(isset($settings)){ 
             
          $local_business_details = array();          
          $wpdb->query('START TRANSACTION');
          $errorDesc = array();
          $user_id = get_current_user_id();
           
                    if($settings['seop_local_name'] !=''){ 
                        
                         $schema_post = array(
                            'post_author' => $user_id,                                                            
                            'post_status' => 'publish',                    
                            'post_type'   => 'saswp',                    
                        );   
                         
                    $schema_post['post_title'] = 'Organization (Migrated from SEO Pressor)';
                                      
                    if(isset($settings['seop_local_name'])){
                        
                     $schema_post['post_title'] = $settings['seop_local_name'].'(Migrated from WP SEO Plugin)'; 
                     
                    }
                    if(isset($settings['seop_home_logo'])){
                        
                       $image_details 	= wp_get_attachment_image_src($settings['seop_home_logo'], 'full');
              
                       $local_business_details['local_business_logo'] = array(
                                'url'           =>$image_details[0],  
                                'id'            =>$settings['site_image'],
                                'height'        =>$image_details[1],
                                'width'         =>$image_details[2],
                                'thumbnail'     =>$image_details[0]        
                            ); 
                    }
                                                          
                    if(isset($settings['seop_local_website'])){
                      $local_business_details['local_website'] = $settings['seop_local_website'];  
                    }
                    
                    if(isset($settings['seop_local_city'])){
                        $local_business_details['local_city'] = $settings['seop_local_city'];
                    }
                    if(isset($settings['seop_local_state'])){
                        $local_business_details['local_state'] = $settings['seop_local_state'];
                    }
                    if(isset($settings['seop_local_postcode'])){
                        $local_business_details['local_postal_code'] = $settings['seop_local_postcode'];
                    }
                    if(isset($settings['seop_local_address'])){
                        $local_business_details['local_street_address'] = $settings['seop_local_address'];
                    }                                                                               
                    $post_id = wp_insert_post($schema_post);
                    $result  = $post_id;
                    $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");
                     
                    $data_group_array = array();   
                    
                    $data_group_array['group-0'] =array(
                                            'data_array' => array(
                                                        array(
                                                        'key_1' => 'post_type',
                                                        'key_2' => 'equal',
                                                        'key_3' => 'post',
                                              )
                                            )               
                                           );                                        
                    
                    $saswp_meta_key = array(
                        'schema_type'                  => 'local_business',
                        'data_group_array'             => $data_group_array,
                        'imported_from'                => 'wp_seo_schema',
                        'saswp_local_business_details' => $local_business_details,
                        'saswp_dayofweek'              => $opening_hours,        
                     );
                
                    foreach ($saswp_meta_key as $key => $val){                     
                        update_post_meta($post_id, $key, $val);  
                    }
                    if(is_wp_error($result)){
                        $errorDesc[] = $result->get_error_message();
                    }
                    }
                                                                                                            
                $get_options   = get_option('sd_data');
                $merge_options = array_merge($get_options, $social_fields);
                $result        = update_option('sd_data', $merge_options);
          
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);           
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }               
         }                        
    }
    
    function saswp_import_wp_seo_schema_plugin_data(){
        
         global $KcSeoWPSchema;
         global $wpdb;
         $settings = get_option($KcSeoWPSchema->options['settings']); 
         
         if(isset($settings)){
             
          $saswp_plugin_options   = array();   
          $local_business_details = array();          
          $wpdb->query('START TRANSACTION');
          $errorDesc = array();
          $user_id = get_current_user_id();
          
                    if($settings['site_type'] !='Organization'){
                        
                         $schema_post = array(
                            'post_author' => $user_id,                                                            
                            'post_status' => 'publish',                    
                            'post_type'   => 'saswp',                    
                        );                        
                    $schema_post['post_title'] = 'Organization (Migrated from WP SEO Plugin)';
                                      
                    if(isset($settings['type_name'])){
                     $schema_post['post_title'] = $settings['type_name'].'(Migrated from WP SEO Plugin)';    
                    }
                    if(isset($settings['site_image'])){
                       $image_details 	= wp_get_attachment_image_src($settings['site_image'], 'full');
              
                       $local_business_details['local_business_logo'] = array(
                                'url'           =>$image_details[0],  
                                'id'            =>$settings['site_image'],
                                'height'        =>$image_details[1],
                                'width'         =>$image_details[2],
                                'thumbnail'     =>$image_details[0]        
                            ); 
                    }
                    if(isset($settings['site_price_range'])){
                        $local_business_details['local_price_range'] = $settings['site_price_range']; 
                    }
                    if(isset($settings['site_telephone'])){
                        $local_business_details['local_phone'] = $settings['site_telephone'];
                    }                                        
                    if(isset($settings['web_url'])){
                      $local_business_details['local_website'] = $settings['web_url'];  
                    }
                    
                    if(isset($settings['address']['locality'])){
                        $local_business_details['local_city'] = $settings['site_telephone'];
                    }
                    if(isset($settings['address']['region'])){
                        $local_business_details['local_state'] = $settings['address']['region'];
                    }
                    if(isset($settings['address']['postalcode'])){
                        $local_business_details['local_postal_code'] = $settings['address']['postalcode'];
                    }
                    if(isset($settings['address']['street'])){
                        $local_business_details['local_street_address'] = $settings['site_telephone'];
                    }
                        
                    $post_id = wp_insert_post($schema_post);
                    $result  = $post_id;
                    $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                    $wpdb->query("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");
                     
                    $data_group_array = array();    
                    
                    $data_group_array['group-0'] =array(
                                            'data_array' => array(
                                                        array(
                                                        'key_1' => 'post_type',
                                                        'key_2' => 'equal',
                                                        'key_3' => 'post',
                                              )
                                            )               
                                           );                                        
                    
                    $saswp_meta_key = array(
                        'schema_type'                  => 'local_business',
                        'data_group_array'             => $data_group_array,
                        'imported_from'                => 'wp_seo_schema',
                        'saswp_local_business_details' => $local_business_details
                     );
                
                    foreach ($saswp_meta_key as $key => $val){                     
                        update_post_meta($post_id, $key, $val);  
                    }
                    if(is_wp_error($result)){
                        $errorDesc[] = $result->get_error_message();
                    }
                    
                    }
                                                                
          if(isset($settings['person']['name'])){
           $saswp_plugin_options['sd-person-name'] =  $settings['person']['name'];     
          }
          
          if(isset($settings['person']['jobTitle'])){
           $saswp_plugin_options['sd-person-job-title'] =  $settings['person']['jobTitle'];        
          }
           
          if(isset($settings['person']['image'])){
              $image_details 	= wp_get_attachment_image_src($settings['person']['image'], 'full');
              
              $saswp_plugin_options['sd-person-image'] = array(
                                'url'           =>$image_details[0],  
                                'id'            =>$settings['organization_logo'],
                                'height'        =>$image_details[1],
                                'width'         =>$image_details[2],
                                'thumbnail'     =>$image_details[0]        
                            );                                                  
          }         
               
          if(isset($settings['organization_logo'])){
              $image_details 	= wp_get_attachment_image_src($settings['organization_logo'], 'full');	   
              
              $saswp_plugin_options['sd_logo'] = array(
                                'url'           =>$image_details[0],  
                                'id'            =>$settings['organization_logo'],
                                'height'        =>$image_details[1],
                                'width'         =>$image_details[2],
                                'thumbnail'     =>$image_details[0]        
                            );                               
          }          
          if(isset($settings['contact']['contactType'])){
              $saswp_plugin_options['saswp_contact_type'] =  $settings['contact']['contactType']; 
              $saswp_plugin_options['saswp_kb_contact_1'] =  1; 
          }
          if(isset($settings['contact']['telephone'])){
              $saswp_plugin_options['saswp_kb_telephone'] =  $settings['contact']['telephone'];    
          }                   
          if(isset($settings['sitename'])){
              $saswp_plugin_options['sd_name'] =  $settings['sitename']; 
          }
          
          if(isset($settings['siteurl'])){
              $saswp_plugin_options['sd_url'] =  $settings['sitename'];    
          }                
                $get_options   = get_option('sd_data');
                $merge_options = array_merge($get_options, $saswp_plugin_options);
                $result        = update_option('sd_data', $merge_options);
          
           if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);             
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }               
         }
                 
       
    }
    function saswp_import_schema_pro_plugin_data(){           
                                                                     
        $schema_post = array();
        global $wpdb;
        $user_id = get_current_user_id();
        
        $all_schema_post = get_posts(
                    array(
                            'post_type' 	 => 'aiosrs-schema',                                                                                   
                            'posts_per_page'     => -1,   
                            'post_status'        => 'any',
                    )
                 );   
        
        if($all_schema_post){
            // begin transaction
            $wpdb->query('START TRANSACTION');
            $errorDesc = array();
            foreach($all_schema_post as $schema){    
                
                $schema_post = array(
                    'post_author'           => $user_id,
                    'post_date'             => $schema->post_date,
                    'post_date_gmt'         => $schema->post_date_gmt,
                    'post_content'          => $schema->post_content,
                    'post_title'            => $schema->post_title. ' (Migrated from Schema_pro plugin)',
                    'post_excerpt'          => $schema->post_excerpt,
                    'post_status'           => $schema->post_status,
                    'comment_status'        => $schema->comment_status,
                    'ping_status'           => $schema->ping_status,
                    'post_password'         => $schema->post_password,
                    'post_name'             => $schema->post_name,
                    'to_ping'               => $schema->to_ping,
                    'pinged'                => $schema->pinged,
                    'post_modified'         => $schema->post_modified,
                    'post_modified_gmt'     => $schema->post_modified_gmt,
                    'post_content_filtered' => $schema->post_content_filtered,
                    'post_parent'           => $schema->post_parent,                                        
                    'menu_order'            => $schema->menu_order,
                    'post_type'             => 'saswp',
                    'post_mime_type'        => $schema->post_mime_type,
                    'comment_count'         => $schema->comment_count,
                    'filter'                => $schema->filter,                    
                );   
                
                $post_id = wp_insert_post($schema_post);
                $result  = $post_id;
                $guid    = get_option('siteurl') .'/?post_type=saswp&p='.$post_id;                
                $wpdb->get_results("UPDATE ".$wpdb->prefix."posts SET guid ='".esc_sql($guid)."' WHERE ID ='".esc_sql($post_id)."'");   
                
                $schema_post_meta           = get_post_meta($schema->ID, $key='', true );                 
                $schema_post_types          = get_post_meta($schema->ID, $key='bsf-aiosrs-schema-type', true );                   
                $schema_post_meta_box       = get_post_meta($schema->ID, $key='bsf-aiosrs-'.$schema_post_types, true );                
                $schema_enable_location     = get_post_meta($schema->ID, $key='bsf-aiosrs-schema-location', true );
                $schema_exclude_location    = get_post_meta($schema->ID, $key='bsf-aiosrs-schema-exclusion', true );
                
                $data_array = array();
                
                if($schema_exclude_location){
                    
                   $exclude_rule = $schema_exclude_location['rule'];                     
                   $fields = array_flip($exclude_rule);
                   
                   unset($fields['specifics']);
                   
                   $exclude_rule = array_flip($fields);                   
                   $exclude_specific = $schema_exclude_location['specific'];  
                  
                   
                   foreach($exclude_rule as $rule){
                       
                       if($rule =='basic-singulars'){
                           
                       $data_array['data_array'][] =array(                                                     
                            'key_1' => 'post_type',
                            'key_2' => 'not_equal',
                            'key_3' => 'post',                            
                         );
                       
                      }else{
                          
                       $explode = explode("|", $rule);   
                       $data_array['data_array'][] =array(                                                      
                            'key_1' => 'post_type',
                            'key_2' => 'not_equal',
                            'key_3' => $explode[0],                                                                  
                         );
                       
                      }                                                                   
                   }                                                           
                   
                   foreach ($exclude_specific as $rule){
                                             
                       $explode = explode("-", $rule);  
                       $specific_post_name = $explode[0];
                       $specific_post_id   = $explode[1];
                       
                       if($specific_post_name =='post'){
                         
                         $specific_post_type = get_post_type($specific_post_id); 
                         
                          $data_array['data_array'][] =array(                                                      
                            'key_1' => $specific_post_type,
                            'key_2' => 'not_equal',
                            'key_3' => $specific_post_id,                                                      
                         );  
                          
                       }
                       
                       if($specific_post_name =='tax'){
                           
                           $data_array['data_array'][] =array(                                                      
                            'key_1' => 'post_category',
                            'key_2' => 'not_equal',
                            'key_3' => $specific_post_id,                                                      
                         );
                           
                       }
                                                                                                                                                                                                                                     
                    }
                    
                    $temp_data_array = $data_array['data_array'];
                    $temp_two_array = $data_array['data_array'];                
                    $j =0;      
                    
                    foreach($temp_two_array as $key => $val){
                        
                        $index =0;     
                        
                        foreach($temp_data_array as $t=>$tval){

                        if(($val['key_1'] == $tval['key_1']) && ($val['key_2'] == $tval['key_2']) && ($val['key_3'] == $tval['key_3'])){
                          $index++;   
                            if($index>1 ){
                                unset($temp_two_array[$t]);
                            }
                         }                    

                        }
                    } 
                   $data_array['data_array'] =  array_values($temp_two_array);
                }               
                                                             
                $data_group_array = array();
                
                if($schema_enable_location){
                    
                   $enable_rule = $schema_enable_location['rule'];  
                   $fields      = array_flip($enable_rule);
                   
                   unset($fields['specifics']);
                   
                   $enable_rule     = array_flip($fields);                   
                   $enable_specific = $schema_enable_location['specific'];                    
                                                                                                                       
                    $i=0;
                    foreach ($enable_rule as $rule){
                       
                      if($rule =='basic-singulars'){
                          
                       $data_group_array['group-'.$i] =array(
                           
                          'data_array' => array(
                            array(
                            'key_1' => 'post_type',
                            'key_2' => 'equal',
                            'key_3' => 'post',
                            )
                          ) 
                           
                         );  
                       
                      }else{
                          
                       $explode = explode("|", $rule);   
                       
                       $data_group_array['group-'.$i] =array(
                           
                          'data_array' => array(
                            array(
                            'key_1' => 'post_type',
                            'key_2' => 'equal',
                            'key_3' => $explode[0],
                            )
                          ) 
                           
                         );   
                       
                      } 
                       if(isset($data_array['data_array'])){
                           
                            $data_group_array['group-'.$i]['data_array'] = array_merge($data_group_array['group-'.$i]['data_array'],$data_array['data_array']);                                                                      
                            
                       }
                    $i++;  
                    
                    }
                    
                    foreach ($enable_specific as $rule){
                                             
                       $explode            = explode("-", $rule);  
                       $specific_post_name = $explode[0];
                       $specific_post_id   = $explode[1];
                       
                       if($specific_post_name =='post'){
                         
                         $specific_post_type = get_post_type($specific_post_id);  
                         
                         $data_group_array['group-'.$i] =array(
                             
                                'data_array' => array(
                                  array(
                                  'key_1' => $specific_post_type,
                                  'key_2' => 'equal',
                                  'key_3' => $specific_post_id,
                                  )
                                )  
                             
                         );  
                       }
                       
                       if($specific_post_name =='tax'){
                           
                           $data_group_array['group-'.$i] =array(
                               
                                'data_array' => array(
                                 array(
                                 'key_1' => 'post_category',
                                 'key_2' => 'equal',
                                 'key_3' => $specific_post_id,
                                 )
                               )
                               
                         );
                           
                       }
                       if(isset($data_array['data_array'])){
                           
                               $data_group_array['group-'.$i]['data_array'] = array_merge($data_group_array['group-'.$i]['data_array'],$data_array['data_array']);                                                                                                                                                                           
                       
                       }
                     
                    $i++;  
                    
                    }                  
                }                                
                $schema_type  = '';  
                $local_name   = '';
                $local_image  = '';
                $local_phone  = '';
                $local_url    = '';
                $local_url    = '';
                
                if(isset($schema_post_types)){
                    
                  $schema_type = ucfirst($schema_post_types);  
                  
                  
                }
                if($schema_type =='Video-object'){
                    
                    $schema_type = 'VideoObject';
                    
                }
                $local_business_details = array();
                
                if($schema_type =='Local-business'){
                    
                    $schema_type = 'local_business';
                    
                    if(isset($schema_post_meta_box['telephone'])){
                        $local_business_details['local_phone'] = $schema_post_meta_box['telephone'];
                    }
                    if(isset($schema_post_meta_box['image'])){
                        $local_business_details['local_business_logo']['url'] = $schema_post_meta_box['image'];
                    }
                    if(isset($schema_post_meta_box['price-range'])){
                        $local_business_details['local_price_range'] = $schema_post_meta_box['price-range'];
                    }
                    if(isset($schema_post_meta_box['location-postal'])){
                        $local_business_details['local_postal_code'] = $schema_post_meta_box['location-postal'];
                    }
                    if(isset($schema_post_meta_box['location-region'])){
                        $local_business_details['local_state'] = $schema_post_meta_box['location-region']; 
                    }
                    if(isset($schema_post_meta_box['location-street'])){
                        $local_business_details['local_street_address'] = $schema_post_meta_box['location-street']; 
                    }
                    if(isset($schema_post_meta_box['url'])){
                       $local_business_details['local_website'] = $schema_post_meta_box['url'];  
                    }                                        
                }                  
                $saswp_meta_key = array(
                    
                    'schema_type'                   => $schema_type,
                    'data_group_array'              => $data_group_array,
                    'imported_from'                 => 'schema_pro',
                    'saswp_local_business_details'  => $local_business_details
                        
                );
                
                foreach ($saswp_meta_key as $key => $val){   
                    
                    update_post_meta($post_id, $key, $val);  
                    
                }   
                if(is_wp_error($result)){
                    $errorDesc[] = $result->get_error_message();
                }
            }                                      
              //Importing settings starts here              
              
                $schema_pro_general_settings = get_option('wp-schema-pro-general-settings');  
                $schema_pro_social_profile   = get_option('wp-schema-pro-social-profiles');
                $schema_pro_global_schemas   = get_option('wp-schema-pro-global-schemas');
                $schema_pro_settings         = get_option('aiosrs-pro-settings');                                 
                $logo                        = wp_get_attachment_image_src( $schema_pro_general_settings['site-logo-custom'] , 'full' );
                             
                $saswp_plugin_options = array(
                    
                    'sd_logo'                   => array(
                                                'url'           => $logo[0],  
                                                'id'            => $schema_pro_general_settings['site-logo-custom'],
                                                'height'        => $logo[1],
                                                'width'         => $logo[2],
                                                'thumbnail'     => $logo[0]        
                    ),    
                    
                    'saswp_kb_contact_1'        => 0,                                                                            
                    //AMP Block           
                    'saswp-for-amp'             => 1, 
                    'saswp-for-wordpress'       => 1,      
                    'saswp-logo-width'          => '60',
                    'saswp-logo-height'         => '60',                    
                    'sd_initial_wizard_status'  => 1,
                                        
               );                
                if(isset($schema_pro_social_profile['facebook'])){
                  $saswp_plugin_options['sd_facebook'] =  $schema_pro_social_profile['facebook']; 
                  $saswp_plugin_options['saswp-facebook-enable'] =  1; 
                }
                if(isset($schema_pro_social_profile['twitter'])){
                  $saswp_plugin_options['sd_twitter'] =  $schema_pro_social_profile['twitter']; 
                  $saswp_plugin_options['saswp-twitter-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['google-plus'])){
                  $saswp_plugin_options['sd_google_plus'] =  $schema_pro_social_profile['google-plus']; 
                  $saswp_plugin_options['saswp-google-plus-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['instagram'])){
                  $saswp_plugin_options['sd_instagram'] =  $schema_pro_social_profile['instagram']; 
                  $saswp_plugin_options['saswp-instagram-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['youtube'])){
                  $saswp_plugin_options['sd_youtube'] =  $schema_pro_social_profile['youtube']; 
                  $saswp_plugin_options['saswp-youtube-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['linkedin'])){
                  $saswp_plugin_options['sd_linkedin'] =  $schema_pro_social_profile['linkedin']; 
                  $saswp_plugin_options['saswp-linkedin-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['pinterest'])){
                  $saswp_plugin_options['sd_pinterest'] =  $schema_pro_social_profile['pinterest']; 
                  $saswp_plugin_options['saswp-pinterest-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['soundcloud'])){
                  $saswp_plugin_options['sd_soundcloud'] =  $schema_pro_social_profile['soundcloud']; 
                  $saswp_plugin_options['saswp-soundcloud-enable'] =  1;
                }
                if(isset($schema_pro_social_profile['tumblr'])){
                  $saswp_plugin_options['sd_tumblr'] =  $schema_pro_social_profile['tumblr']; 
                  $saswp_plugin_options['saswp-tumblr-enable'] =  1;
                }                
                if(isset($schema_pro_general_settings['site-represent'])){
                                                           
                  $saswp_plugin_options['saswp_kb_type'] = ucfirst($schema_pro_general_settings['site-represent']);  
                  $saswp_plugin_options['sd_name'] = $schema_pro_general_settings['site-name'];
                  $saswp_plugin_options['sd-person-name'] = $schema_pro_general_settings['person-name'];
                }                
                if(isset($schema_pro_global_schemas['about-page'])){
                  $saswp_plugin_options['sd_about_page'] = $schema_pro_global_schemas['about-page'];  
                }
                if(isset($schema_pro_global_schemas['contact-page'])){
                  $saswp_plugin_options['sd_contact_page'] = $schema_pro_global_schemas['contact-page'];  
                }
                if(isset($schema_pro_global_schemas['breadcrumb'])){
                  $saswp_plugin_options['saswp_breadcrumb_schema'] = $schema_pro_global_schemas['breadcrumb'];  
                }                                              
                $get_options = get_option('sd_data');
                $merge_options = array_merge($get_options, $saswp_plugin_options);
                update_option('sd_data', $merge_options);
               
              
            if ( count($errorDesc) ){
              echo implode("\n<br/>", $errorDesc);              
              $wpdb->query('ROLLBACK');             
            }else{
              $wpdb->query('COMMIT'); 
              return true;
            }            
        }
                             
    }
    

//Function to expand html tags form allowed html tags in wordpress    
function saswp_expanded_allowed_tags() {
            $my_allowed = wp_kses_allowed_html( 'post' );
            // form fields - input
            $my_allowed['input']  = array(
                    'class'        => array(),
                    'id'           => array(),
                    'name'         => array(),
                    'value'        => array(),
                    'type'         => array(),
                    'style'        => array(),
                    'placeholder'  => array(),
                    'maxlength'    => array(),
                    'checked'      => array(),
                    'readonly'     => array(),
                    'disabled'     => array(),
                    'width'        => array(),  
                    'data-id'      => array(),
                    'checked'      => array()
            );
            $my_allowed['hidden']  = array(                    
                    'id'           => array(),
                    'name'         => array(),
                    'value'        => array(),
                    'type'         => array(), 
                    'data-id'         => array(), 
            );
            //number
            $my_allowed['number'] = array(
                    'class'        => array(),
                    'id'           => array(),
                    'name'         => array(),
                    'value'        => array(),
                    'type'         => array(),
                    'style'        => array(),                    
                    'width'        => array(),                    
            ); 
            //textarea
             $my_allowed['textarea'] = array(
                    'class' => array(),
                    'id'    => array(),
                    'name'  => array(),
                    'value' => array(),
                    'type'  => array(),
                    'style'  => array(),
                    'rows'  => array(),                                                            
            );              
            // select
            $my_allowed['select'] = array(
                    'class'  => array(),
                    'id'     => array(),
                    'name'   => array(),
                    'value'  => array(),
                    'type'   => array(),                    
            );
            // checkbox
            $my_allowed['checkbox'] = array(
                    'class'  => array(),
                    'id'     => array(),
                    'name'   => array(),
                    'value'  => array(),
                    'type'   => array(),  
                    'disabled'=> array(),  
            );
            //  options
            $my_allowed['option'] = array(
                    'selected' => array(),
                    'value' => array(),
            );                       
            // style
            $my_allowed['style'] = array(
                    'types' => array(),
            );
            return $my_allowed;
        }    
function saswp_admin_link($tab = '', $args = array()){
           
            $page = 'structured_data_options';
            
            if ( ! is_multisite() ) {
                    $link = admin_url( 'admin.php?page=' . $page );
            }
            else {
                    $link = network_admin_url( 'admin.php?page=' . $page );
            }

            if ( $tab ) {
                    $link .= '&tab=' . $tab;
            }

            if ( $args ) {
                    foreach ( $args as $arg => $value ) {
                            $link .= '&' . $arg . '=' . urlencode( $value );
                    }
            }

            return esc_url($link);
}
function saswp_get_tab( $default = '', $available = array() ) {

            $tab = isset( $_GET['tab'] ) ? sanitize_text_field(wp_unslash($_GET['tab'])) : $default;            
            if ( ! in_array( $tab, $available ) ) {
                    $tab = $default;
            }

            return $tab;
        }

add_action('plugins_loaded', 'saswp_defaultSettings' );

             $sd_data=array();                
function saswp_defaultSettings(){
    
            global $sd_data;    
            $sd_name = 'default';
            $logo    = array();
            $bloginfo = get_bloginfo('name', 'display'); 
            
            if($bloginfo){
                
            $sd_name =$bloginfo;
            
            }
            
            $current_url    = get_home_url();           
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            
            if($custom_logo_id){                
                $logo       = wp_get_attachment_image_src( $custom_logo_id , 'full' );               
            }
            
            $user_id        = get_current_user_id();
            $username       = '';
            
            if($user_id>0){
                
                $user_info = get_userdata($user_id);
                $username = $user_info->data->display_name;
                
            }
            $defaults = array(
                    //General Block
                    'sd_about_page'     => '',
                    'sd_contact_page'   => '',         
                    //knowledge Block
                    'saswp_kb_type'     => 'Organization',    
                    'sd_name'           => $sd_name,   
                    'sd_alt_name'       => $sd_name,
                    'sd_url'            => $current_url,
                    'sd_logo'           => array(
                                'url'           => $logo[0],
                                'id'            => $custom_logo_id,
                                'height'        => $logo[2],
                                'width'         => $logo[1],
                                'thumbnail'     => $logo[0]        
                            ),
                    'sd-person-name'     => $username,                    
                    'sd-person-job-title'=> '',
                    'sd-person-url'      => $current_url,
                    'sd-person-image'    => array(
                                'url'           =>'',
                                'id'            =>'',
                                'height'        =>'',
                                'width'         =>'',
                                'thumbnail'     =>'' 
                                ),
                    'sd-person-phone-number' => '',
                    'saswp_kb_telephone'     => '',
                    'saswp_contact_type'     => '',
                    'saswp_kb_contact_1'     => 0,
                    //Social
                    'sd_facebook'            => '',
                    'sd_twitter'             => '',
                    'sd_google_plus'         => '',
                    'sd_instagram'           => '',
                    'sd_youtube'             => '',
                    'sd_linkedin'            => '',
                    'sd_pinterest'           => '',
                    'sd_soundcloud'          => '',
                    'sd_tumblr'              => '',


                    'sd-data-logo-ampforwp' => array(
                        
                        'url'       => $logo[0],
                        'id'        => $custom_logo_id,
                        'height'    => $logo[2],
                        'width'     => $logo[1],
                        'thumbnail' => $logo[0]        
                    
                    ),

                    //AMP Block           
                    'saswp-for-amp'       => 1, 
                    'saswp-for-wordpress' => 1,      
                    'saswp-logo-width'    => '60',
                    'saswp-logo-height'   => '60',
                    
                    'sd_default_image' => array(
                        'url'       => $logo[0],
                        'id'        => $custom_logo_id,
                        'height'    => $logo[2],
                        'width'     => $logo[1],
                        'thumbnail' => $logo[0]        
                    ),
                    'sd_default_image_width'   => $logo[1],
                    'sd_default_image_height'  => $logo[2],
                    'sd_initial_wizard_status' => 1,                                        

            );	            
            $sd_data = $settings = get_option( 'sd_data', $defaults);     
            
            return $settings;
            
        }
function saswp_frontend_enqueue(){      
      wp_enqueue_style( 'saswp-style', SASWP_PLUGIN_URL . 'admin_section/css/saswp-style.css', false , SASWP_VERSION );       
                
  }
  add_action( 'wp_enqueue_scripts', 'saswp_frontend_enqueue' );
  
 function saswp_enque_amp_script(){
     
        global $sd_data;         
        $saswp_review_details = esc_sql ( get_post_meta(get_the_ID(), 'saswp_review_details', true)); 
        
        $saswp_review_item_enable = 0;
        
        if(isset($saswp_review_details['saswp-review-item-enable'])){
            
         $saswp_review_item_enable =  $saswp_review_details['saswp-review-item-enable'];  
         
        }         
        
        if($sd_data['saswp-review-module']== 1 && $saswp_review_item_enable == 1){                                  
     ?>
        .saswp-pc-wrap{
            background-color: #004f74;
            padding: 15px;
            color: #fff;
            display: flex;
            width:auto;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .saswp-pc-wrap .saswp-lst span{
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 10px;
            display: inline-block;
            line-height: 1.3;
        }
        .saswp-pc-wrap .saswp-lst{
            flex:1 0 42%;
        }
        .saswp-pc-wrap .saswp-lst ul{
            margin:0;
        }
        .saswp-pc-wrap .saswp-lst p{
            list-style-type: none;
            font-size: 15px;
            font-weight: lighter;
            line-height: 1.2;
            margin-bottom: 10px;
            position: relative;
            padding-left: 20px;
            color:#eee;
        }
        .saswp-pc-wrap .saswp-lst p:before{
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            background-color: #ccc;
            left: 0px;
            top: 6px;
            border-radius: 10px;
        }
        .sgl .saswp-rvw {
            width: 100%;
            margin-bottom: 34px;
            font-size: 13px;
            border-bottom: 1px solid #ededed;
        }
        .saswp-rvw-hd span {
            background-color: #222;
            color: #fff;
            display: inline-block;
            font-size: 15px;
            line-height: 1.4;
            padding: 8px 12px 6px;
            margin: 26px 0px;
        }
        .saswp-rvw tbody{
            width:100%;
            display:inline-block;
        }
        .saswp-rvw td {
            padding: 7px 14px;
        }
        .sgl table td, .saswp-rvw td {
            border: 1px solid #ededed;
        }
        .saswp-rvw-sm span{
            background-color: #222;
            color: #fff;
            display: inline-block;
            padding: 8px 12px 6px;
            margin-bottom: 13px;
            position: relative;
            font-size: 15px;
            line-height: 1.2;
        }
        .saswp-rvw-fs {
            line-height: 1.5;
            font-size: 48px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .saswp-rvw-ov .ovs {
            font-size: 11px;
            font-weight:600;
        }
        .sgl .saswp-rvw tr td{
            background:#fff;
            width:100%;
        }
        .sgl .saswp-rvw tr:hover td {
            background-color: #fcfcfc;
        }
        .saswp-rvw .saswp-rvw-sm {
            padding: 21px 14px;
        }
        .str-ic{
            font-size: 18px;
            line-height: 1.2;
        }
        .saswp-rvw-str{
            display: inline-flex;
            width: 100%;
        }
        .saswp-rvw-ov{
            text-align:center;
        }
        .saswp-rvw-str .half-str{
            display:inline-block;
            width: 20px;
            height: 16px;
            background-repeat: no-repeat;
            background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/half_star.png'); ?>);
        }
        .saswp-rvw-str .str-ic{
            display:inline-block;
            width: 20px;
            height: 16px;
            background-repeat: no-repeat;
            background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/full_star.png'); ?>);
        }
        .saswp-rvw-str .df-clr{
            display:inline-block;
            width: 20px;
            height: 16px;
            background-repeat: no-repeat;
            background-image: url(<?php echo esc_url(SASWP_DIR_URI.'/admin_section/images/blank_star.png'); ?>);
        }
        @media(max-width:500px){
            .saswp-pc-wrap{
                display:block;
            }
            .saswp-pc-wrap .saswp-lst{
                margin-bottom:20px;
            }
        }
    <?php
     }
  }
    add_action('amp_post_template_css','saswp_enque_amp_script');