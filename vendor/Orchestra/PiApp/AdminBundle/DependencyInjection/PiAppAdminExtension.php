<?php
/**
 * This file is part of the <Admin> project.
 *
 * @category   Bundle
 * @package    DependencyInjection
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-01-11
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PiApp\AdminBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Loader,
    Symfony\Component\Config\FileLocator;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * @category   Bundle
 * @package    DependencyInjection
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class PiAppAdminExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config  = $this->processConfiguration($configuration, $configs);
        
        $loaderYaml  = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config/service'));
        $loaderYaml->load('services_twig_extension.yml');
        $loaderYaml->load('services_util.yml');
        $loaderYaml->load('services.yml');
        $loaderYaml->load("services_form_validator.yml");
        $loaderYaml->load('services_listener.yml');
        
        //         $PROXY_HOST = "proxy.example.com"; // Proxy server address
        //         $PROXY_PORT = "1234";    // Proxy server port
        //         $PROXY_USER = "LOGIN";    // Username
        //         $PROXY_PASS = "PASSWORD";   // Password
        //         // Username and Password are required only if your proxy server needs basic authentication
        //         $auth = base64_encode("$PROXY_USER:$PROXY_PASS");
        //         stream_context_set_default(
        //          array(
        //           'http' => array(
        //            'proxy' => "tcp://$PROXY_HOST:$PROXY_PORT",
        //            'request_fulluri' => true,
        //            'header' => "Proxy-Authorization: Basic $auth"
        //            // Remove the 'header' option if proxy authentication is not required
        //           )
        //          )
        //         );        
        
        /**
         * Admin config parameter
         */
        if (isset($config['admin'])){
            if (isset($config['admin']['context_menu_theme']))
                $container->setParameter('pi_app_admin.admin.context_menu_theme', $config['admin']['context_menu_theme']);
            if (isset($config['admin']['grid_index_css']))
                $container->setParameter('pi_app_admin.admin.grid_index_css', 'bundles/piappadmin/css/grid/' . $config['admin']['grid_index_css']);
            if (isset($config['admin']['grid_show_css']))
                $container->setParameter('pi_app_admin.admin.grid_show_css', 'bundles/piappadmin/css/grid/' . $config['admin']['grid_show_css']);
            if (isset($config['admin']['theme_css']))
                $container->setParameter('pi_app_admin.admin.theme_css', 'bundles/piappadmin/css/themes/'.$config['admin']['theme_css'].'/jquery-ui.css');
        }        
        
        /**
         * Page config parameter
         */
        if (isset($config['page'])){
            if (isset($config['page']['homepage_deletewidget']))
                $container->setParameter('pi_app_admin.page.homepage_deletewidget', $config['page']['homepage_deletewidget']);
            if (isset($config['page']['page_management_by_user_only']))
                $container->setParameter('pi_app_admin.page.management_by_user_only', $config['page']['page_management_by_user_only']);
            
            if (isset($config['page']['route']) && isset($config['page']['route']['with_prefix_locale'])) {
            	$container->setParameter('pi_app_admin.page.route.with_prefix_locale', $config['page']['route']['with_prefix_locale']);
            }          
            if (isset($config['page']['route']) && isset($config['page']['route']['single_slug'])) {
            	$container->setParameter('pi_app_admin.page.route.single_slug', $config['page']['route']['single_slug']);
            }
            
            if (isset($config['page']['esi']) && isset($config['page']['esi']['force_private_response_for_all'])) {
            	$container->setParameter('pi_app_admin.page.esi.force_private_response_for_all', $config['page']['esi']['force_private_response_for_all']);
            } 
            if (isset($config['page']['esi']) && isset($config['page']['esi']['force_private_response_only_with_authentication'])) {
            	$container->setParameter('pi_app_admin.page.esi.force_private_response_only_with_authentication', $config['page']['esi']['force_private_response_only_with_authentication']);
            } 
            if (isset($config['page']['esi']) && isset($config['page']['esi']['disable_after_post_request'])) {
            	$container->setParameter('pi_app_admin.page.esi.disable_after_post_request', $config['page']['esi']['disable_after_post_request']);
            }       

            if (isset($config['page']['widget']) && isset($config['page']['widget']['render_service_with_ajax'])) {
            	$container->setParameter('pi_app_admin.page.widget.render_service_with_ajax', $config['page']['widget']['render_service_with_ajax']);
            }      
            if (isset($config['page']['widget']) && isset($config['page']['widget']['ajax_disable_after_post_request'])) {
            	$container->setParameter('pi_app_admin.page.widget.ajax_disable_after_post_request', $config['page']['widget']['ajax_disable_after_post_request']);
            }
            
            if (isset($config['page']['scop']) && isset($config['page']['scop']['authorized'])) {
            	$container->setParameter('pi_app_admin.page.scop.authorized', $config['page']['scop']['authorized']);
            }
            if (isset($config['page']['scop']) && isset($config['page']['scop']['globals'])) {
            	$container->setParameter('pi_app_admin.page.scop.globals', $config['page']['scop']['globals']);
            }  
            if( $config['page']['scop']['browscap']['cache_dir'] === null ) {
            	$config['page']['scop']['browscap']['cache_dir'] = $container->getParameter('kernel.cache_dir');
            }
            foreach ($config['page']['scop']['browscap'] as $k => $v) {
            	$container->setParameter('pi_app_admin.page.scop.browscap.' . $k, $v);
            }
                                                
            if (isset($config['page']['refresh']) && isset($config['page']['refresh']['allpage'])) {
            	$container->setParameter('pi_app_admin.page.refresh.allpage', $config['page']['refresh']['allpage']);
            }
            if (isset($config['page']['refresh']) && isset($config['page']['refresh']['allpage_containing_snippet'])) {
            	$container->setParameter('pi_app_admin.page.refresh.allpage_containing_snippet', $config['page']['refresh']['allpage_containing_snippet']);
            }
            if (isset($config['page']['refresh']) && isset($config['page']['refresh']['css_js_cache_file'])) {
            	$container->setParameter('pi_app_admin.page.refresh.css_js_cache_file', $config['page']['refresh']['css_js_cache_file']);
            }                        
            
            if (isset($config['page']['indexation_authorized_automatically']))
                $container->setParameter('pi_app_admin.page.indexation_authorized_automatically', $config['page']['indexation_authorized_automatically']);
            if (isset($config['page']['switch_layout_mobile_authorized']))
                $container->setParameter('pi_app_admin.page.switch_layout_mobile_authorized', $config['page']['switch_layout_mobile_authorized']);
            if (isset($config['page']['switch_language_browser_authorized']))
                $container->setParameter('pi_app_admin.page.switch_language_browser_authorized', $config['page']['switch_language_browser_authorized']);
            if (isset($config['page']['memcache_enable_all']))
            	$container->setParameter('pi_app_admin.page.memcache_enable_all', $config['page']['memcache_enable_all']);
            
            if (isset($config['page']['seo_redirection']) && isset($config['page']['seo_redirection']['seo_authorized'])) {
            	$container->setParameter('pi_app_admin.page.seo_redirection.seo_authorized', $config['page']['seo_redirection']['seo_authorized']);
            } 
            if (isset($config['page']['seo_redirection']) && isset($config['page']['seo_redirection']['seo_repository'])) {
            	$container->setParameter('pi_app_admin.page.seo_redirection.seo_repository', $config['page']['seo_redirection']['seo_repository']);
            } 
            if (isset($config['page']['seo_redirection']) && isset($config['page']['seo_redirection']['seo_file_name'])) {
            	$container->setParameter('pi_app_admin.page.seo_redirection.seo_file_name', $config['page']['seo_redirection']['seo_file_name']);
            }                                
        }    

        /**
         * Cookies config parameter
         */
        if (isset($config['cookies'])){
            if (isset($config['cookies']['date_expire'])) {
                $container->setParameter('pi_app_admin.cookies.date_expire', $config['cookies']['date_expire']);
            }
            if (isset($config['cookies']['date_interval'])) {
                $container->setParameter('pi_app_admin.cookies.date_interval',$config['cookies']['date_interval']);
            }
            if (isset($config['cookies']['application_id'])) {
            	$container->setParameter('pi_app_admin.cookies.application_id',$config['cookies']['application_id']);
            }
        }        
        
        /**
         * Permission config parameter
         */
        if (isset($config['permission'])){
        	if (isset($config['permission']['restriction_by_roles'])) {
        		$container->setParameter('pi_app_admin.permission.restriction_by_roles', $config['permission']['restriction_by_roles']);
        	}
        	if (isset($config['permission']['authorization']) && isset($config['permission']['authorization']['prepersist'])) {
        		$container->setParameter('pi_app_admin.permission.authorization.prepersist', $config['permission']['authorization']['prepersist']);
        	}
        	if (isset($config['permission']['authorization']) && isset($config['permission']['authorization']['preupdate'])) {
        		$container->setParameter('pi_app_admin.permission.authorization.preupdate', $config['permission']['authorization']['preupdate']);
        	}
        	if (isset($config['permission']['authorization']) && isset($config['permission']['authorization']['preremove'])) {
        		$container->setParameter('pi_app_admin.permission.authorization.preremove', $config['permission']['authorization']['preremove']);
        	}
        	if (isset($config['permission']['prohibition']) && isset($config['permission']['prohibition']['preupdate'])) {
        		$container->setParameter('pi_app_admin.permission.prohibition.preupdate', $config['permission']['prohibition']['preupdate']);
        	}
        	if (isset($config['permission']['prohibition']) && isset($config['permission']['prohibition']['preremove'])) {
        		$container->setParameter('pi_app_admin.permission.prohibition.preremove', $config['permission']['prohibition']['preremove']);
        	}        	        	
        }   

        /**
         * Translation config parameter
         */
        if (isset($config['translation'])){
        	if (isset($config['translation']['defaultlocale_setting'])) {
        		$container->setParameter('pi_app_admin.translation.defaultlocale_setting', $config['translation']['defaultlocale_setting']);
        	} else {
        		$container->setParameter('pi_app_admin.translation.defaultlocale_setting', true);
        	}
        }        
        
        /**
         * Mail config parameter
         */
        if (isset($config['mail'])){
        	if (isset($config['mail']['overloading_mail'])) {
        		$container->setParameter('pi_app_admin.mail.overloading_mail', $config['mail']['overloading_mail']);
        	} else {
        		$container->setParameter('pi_app_admin.mail.overloading_mail', '');
        	}
        }        
        
        /**
         * Form config parameter
         */
        if (isset($config['form'])){
            if (isset($config['form']['show_legend']))
                $container->setParameter('pi_app_admin.form.show_legend', $config['form']['show_legend']);
            if (isset($config['form']['show_child_legend']))
                $container->setParameter('pi_app_admin.form.show_child_legend',$config['form']['show_child_legend']);
            if (isset($config['form']['error_type']))
                $container->setParameter('pi_app_admin.form.error_type',$config['form']['error_type']);
        }
        
        /**
         * Layout config parameter
         */
        if (isset($config['layout'])){
            // PC init config
            if (isset($config['layout']['init_pc'])){
                if (isset($config['layout']['init_pc']['template_name']))
                    $container->setParameter('pi_app_admin.layout.init.pc.template', $config['layout']['init_pc']['template_name']);
                if (isset($config['layout']['init_pc']['route_redirection_name']))
                    $container->setParameter('pi_app_admin.layout.init.pc.redirection', $config['layout']['init_pc']['route_redirection_name']);
            }
            
            // Mobile init config
            if (isset($config['layout']['init_mobile'])){
                if (isset($config['layout']['init_mobile']['template_name']))
                    $container->setParameter('pi_app_admin.layout.init.mobile.template', $config['layout']['init_mobile']['template_name']);
                if (isset($config['layout']['init_mobile']['route_redirection_name']))
                    $container->setParameter('pi_app_admin.layout.init.mobile.redirection', $config['layout']['init_mobile']['route_redirection_name']);
            }

            // Redirection login config
            if (isset($config['layout']['login_role'])){
                if (isset($config['layout']['login_role']['redirection_admin']))
                    $container->setParameter('pi_app_admin.layout.login.admin_redirect', $config['layout']['login_role']['redirection_admin']);
                if (isset($config['layout']['login_role']['redirection_user']))
                    $container->setParameter('pi_app_admin.layout.login.user_redirect', $config['layout']['login_role']['redirection_user']);
                if (isset($config['layout']['login_role']['redirection_subscriber']))
                    $container->setParameter('pi_app_admin.layout.login.subscriber_redirect', $config['layout']['login_role']['redirection_subscriber']);

                if (isset($config['layout']['login_role']['redirection_admin']))
                    $container->setParameter('pi_app_admin.layout.login.admin_template', $config['layout']['login_role']['template_admin']);
                if (isset($config['layout']['login_role']['redirection_user']))
                    $container->setParameter('pi_app_admin.layout.login.user_template', $config['layout']['login_role']['template_user']);
                if (isset($config['layout']['login_role']['redirection_subscriber']))
                    $container->setParameter('pi_app_admin.layout.login.subscriber_template', $config['layout']['login_role']['template_subscriber']);
            }
            
            // Redirection template config
            if (isset($config['layout']['template'])){
                if (isset($config['layout']['template']['template_connection']))
                    $container->setParameter('pi_app_admin.layout.template.connexion', "PiAppTemplateBundle::Template\\Layout\\Connexion\\".$config['layout']['template']['template_connection']);
                if (isset($config['layout']['template']['template_form']))
                    $container->setParameter('pi_app_admin.layout.template.form', "PiAppTemplateBundle:Template\\Form:".$config['layout']['template']['template_form']);
                if (isset($config['layout']['template']['template_grid']))
                    $container->setParameter('pi_app_admin.layout.template.grid', "PiAppTemplateBundle:Template\\Grid:".$config['layout']['template']['template_grid']);
                if (isset($config['layout']['template']['template_flash']))
                    $container->setParameter('pi_app_admin.layout.template.flash', "PiAppTemplateBundle:Template\\Flash:".$config['layout']['template']['template_flash']);
            }
            
            // Layout meta config
            if (isset($config['layout']['meta_head'])){
                if (isset($config['layout']['meta_head']['author']))
                    $container->setParameter('pi_app_admin.layout.meta.author', $config['layout']['meta_head']['author']);
                if (isset($config['layout']['meta_head']['copyright']))
                    $container->setParameter('pi_app_admin.layout.meta.copyright', $config['layout']['meta_head']['copyright']);
                if (isset($config['layout']['meta_head']['og_title_add']))
                	$container->setParameter('pi_app_admin.layout.meta.og_title_add', $config['layout']['meta_head']['og_title_add']);
                if (isset($config['layout']['meta_head']['og_type']))
                    $container->setParameter('pi_app_admin.layout.meta.og_type', $config['layout']['meta_head']['og_type']);
                if (isset($config['layout']['meta_head']['og_image']))
                    $container->setParameter('pi_app_admin.layout.meta.og_image', $config['layout']['meta_head']['og_image']);
                if (isset($config['layout']['meta_head']['og_site_name']))
                    $container->setParameter('pi_app_admin.layout.meta.og_site_name', $config['layout']['meta_head']['og_site_name']);
                if (isset($config['layout']['meta_head']['title']))
                    $container->setParameter('pi_app_admin.layout.meta.title', $config['layout']['meta_head']['title']);
                if (isset($config['layout']['meta_head']['description']))
                    $container->setParameter('pi_app_admin.layout.meta.description', $config['layout']['meta_head']['description']);
                if (isset($config['layout']['meta_head']['keywords']))
                    $container->setParameter('pi_app_admin.layout.meta.keywords', $config['layout']['meta_head']['keywords']);
            }
            
        }          
    
        /**
         * LayoutHead config parameter
         */
        $container->setParameter('js_files', array());
        $container->setParameter('css_files', array());
    }
  
    public function getAlias()
    {
        return 'pi_app_admin';
    }    
   
}
