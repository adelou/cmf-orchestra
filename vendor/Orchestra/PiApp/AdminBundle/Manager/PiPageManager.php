<?php
/**
 * This file is part of the <Admin> project.
 *
 * @category   Admin_Managers
 * @package    Manager
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-01-23
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PiApp\AdminBundle\Manager;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response as Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use PiApp\AdminBundle\Builder\PiPageManagerBuilderInterface;
use PiApp\AdminBundle\Repository\TranslationPageRepository;
use PiApp\AdminBundle\Manager\PiCoreManager;
use PiApp\AdminBundle\Entity\Page;
use PiApp\AdminBundle\Entity\TranslationPage;
use PiApp\AdminBundle\Entity\Block;
use PiApp\AdminBundle\Entity\Widget;

/**
 * Description of the Page manager
 *
 * @category   Admin_Managers
 * @package    Manager
 * 
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class PiPageManager extends PiCoreManager implements PiPageManagerBuilderInterface 
{    
    /**
     * @var \PiApp\AdminBundle\Manager\PiWidgetManager
     */    
    protected $widgetManager;    
    
    /**
     * Constructor.
     *
     * @param ContainerInterface $container The service container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }
    
    /**
     * Returns the render of the current page.
     * 
     * @param string $lang
     * @param bool        $isSetPage
     * 
     * @return string
     * @access    public
     *
     * @author Etienne de Longeaux <etienne_delongeaux@hotmail.com>
     * @since 2012-01-23
     */
    public function render($lang = '', $isSetPage = false)
    {
        // we set the langue
        if (empty($lang))    $lang = $this->language;
        // Initialize page
        if ($this->getCurrentPage()) {
            // we get the current page.
            $page = $this->getCurrentPage();
            // we set the page.
            if ($isSetPage) {
                $this->setPage($page);
            }
        } else {
            if ($this->isAnonymousToken()) {
                // We inform that the page does not exist fi the user is connected.
                $this->setFlash("pi.session.flash.page.notexist", 'notice');
            }
            // we redirect to the public url home page.
            return $this->redirectHomePublicPage();
        }
        // if the page is enabled.
        if ($page && $page->getEnabled()) {
            //     Initialize response
            $response = $this->getResponseByIdAndType('page', $page->getId());            
            // we register only the translation page asked in the $lang value.
            $this->setTranslations($page, $lang);
            // we get the translation of the current page in terms of the lang value.
            $pageTrans	= $this->getTranslationByPageId($page->getId(), $lang);
            // If the translation page is secure and the user is not connected, we return to the home page.
            if ($pageTrans && $pageTrans->getSecure() && $this->isAnonymousToken()) {
                return $this->redirectHomePublicPage();
            }    
            // If the translation page is not authorized to publish, we return to the home page.
            if ($pageTrans && ($pageTrans->getStatus() != TranslationPageRepository::STATUS_PUBLISH) && $this->isAnonymousToken()) {
                return $this->redirectHomePublicPage();
            }        
            // If the translation page is secure and the user is not authorized, we return to the home page.
            if ($pageTrans && $pageTrans->getSecure() && $this->isUsernamePasswordToken()) {
                // Gets all user roles.
                $user_roles                = $this->container->get('bootstrap.Role.factory')->getAllUserRoles();
                // Gets the best role authorized to access to the entity.
                $authorized_page_roles     = $this->container->get('bootstrap.Role.factory')->getBestRoles($pageTrans->getHeritage());                
                $right = false;
                if (is_null($authorized_page_roles)) {
                    $right = true;
                } else {
                    foreach ($authorized_page_roles as $key=>$role_page) {
                        if (in_array($role_page, $user_roles))
                            $right = true;
                    }
                }
                if (!$right) {
                    return $this->redirectHomePublicPage();
                }
            }    
            // Handle 404
            // We don't show the page if :
            // * The page doesn't have a translation set.
            // * the translation doesn't have a published status.
            if (!$pageTrans) {
                // we register all translations page linked to one page.
                $this->setTranslations($page);
                // we get the translation of the current page in another language if it exists.
                $pageTrans	= $this->getTranslationByPageId($page->getId(), $lang);
                if (!$pageTrans) {
                    $page	= $this->setPageByRoute('error_404', true);
                    if (!$page) {
                        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException("We haven't set in the data fixtures the error page message in the $lang locale !");
                    }
                    $response->setStatusCode(404);
                }
            }
            // We set the Etag value
            $id	   = $page->getId();
            $lang_ = $this->language;
            $url_  = $this->container->get('request')->getRequestUri();
            // we register the tag value in the json file if does not exist.
            $this->setJsonFileEtag('page', $id, $lang, array('page-url'=>$url_));
            // Create a Response with a Last-Modified header.
            $response = $this->configureCache($page, $response);
            // Check that the Response is not modified for the given Request.
            if ($response->isNotModified($this->container->get('request'))){
                // We set the reponse
                $this->setResponse($page, $response);
                // return the 304 Response immediately
                return $response;
            } else {
                // or render a template with the $response you've already started
                //$response->setContent($this->container->get('twig')->render($this->renderSource($id, $lang_), array()));
                $response = $this->container->get('pi_app_admin.caching')->renderResponse($this->Etag, array(), $response);
                
                return $response;
            }
        } else {
            return $this->redirectHomePublicPage();
        }
    }
    
    /**
     * Returns the render source of one page.
     *
     * @param string $id
     * @param string $lang
     * @param array     $params
     * @return string
     * @access    public
     *
     * @author Etienne de Longeaux <etienne_delongeaux@hotmail.com>
     * @since 2012-01-31
     */
    public function renderSource($id, $lang = '', $params = null)
    {
    	// we set the page.
        if (!$this->getPageById($id)) {
            $this->setPageById($id);
        }
        // we set the langue
        if (empty($lang))    $lang = $this->language;
        // we init params
        $init_pc_layout        = str_replace("/", "\\\\", $this->getPageById($id)->getLayout()->getFilePc());
        $init_pc_layout        = str_replace("\\", "\\\\", $init_pc_layout);
        $init_mobile_layout    = str_replace("\\", "\\\\", $this->getPageById($id)->getLayout()->getFileMobile());
        if (empty($init_pc_layout)) {
            $init_pc_layout    = $this->container->getParameter('pi_app_admin.layout.init.pc.template');
        }
        if (empty($init_mobile_layout)) {
            $init_mobile_layout = $this->container->getParameter('pi_app_admin.layout.init.mobile.template');
        }
        // we get the translation of the current page in terms of the lang value.
        $pageTrans       = $this->getTranslationByPageId($id, $lang);    //if ($lang == 'fr') print_r($pageTrans->getLangCode()->getId());
        if ($pageTrans instanceof TranslationPage){
            $description = $pageTrans->getMetaDescription();
            $keywords    = $pageTrans->getMetaKeywords();
            $title       = $pageTrans->getMetaTitle();
        } else {
            $description = "";
            $keywords    = "";        
            $title       = "";
        }
        // we return a 404 error if the meta title is a 404 type
        $meta_title = $this->container->get('pi_app_admin.twig.extension.tool')->getTitlePageFunction($lang, $title);
        if ($meta_title == '_error_404_') {
        	throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('The product does not exist');
        }   
        //
        $meta_page = $this->container->get('pi_app_admin.twig.extension.tool')->getMetaPageFunction($lang, array(
                'description' => $description,
                'keywords'    => $keywords,
                'title'       => $meta_title
        ));
        // we get the css file of the page.
        $stylesheet = $this->getPageById($id)->getPageCss();
        // we get the js file of the page.
        $javascript  = $this->getPageById($id)->getPageJs();
        // we create the source page.
        $source  = "{% set layout_screen = app.request.attributes.get('orchestra-screen') %}\n";
        $source .= "{% set is_switch_layout_mobile_authorized = getParameter('pi_app_admin.page.switch_layout_mobile_authorized') %}\n";
        $source .= "{% set is_esi_disable_after_post_request = getParameter('pi_app_admin.page.esi.disable_after_post_request') %}\n";
        $source .= "{% set is_widget_ajax_disable_after_post_request = getParameter('pi_app_admin.page.widget.ajax_disable_after_post_request') %}\n";
        $source .= "{% set app_request_request_count = app.request.request.count() %}\n";
        $source .= "{% if layout_screen is empty or not is_switch_layout_mobile_authorized  %}\n";
        $source .= "{%     set layout_screen = 'layout' %}\n";
        $source .= "{% endif %}\n";
        $source .= "{% if layout_screen in ['layout-poor', 'layout-medium', 'layout-high', 'layout-ultra'] %}\n";
        $source .= "{%     set layout_nav = 'PiAppTemplateBundle::Template\\\Layout\\\Mobile\\\\".$init_mobile_layout."\\\'~ layout_screen ~'.html.twig' %}\n";
        $source .= "{% else %}\n";
        $source .= "{%     set layout_nav = 'PiAppTemplateBundle::Template\\\Layout\\\Pc\\\\".$init_pc_layout."' %}\n";
        $source .= "{% endif %}\n";
        $source .= "{% extends layout_nav %}\n";        
        // we set stylesheets
        if ($stylesheet instanceof \Doctrine\ORM\PersistentCollection) {
            foreach($stylesheet as $s){
                $source     .= "{% stylesheet '".$s->getUrl()."' %} \n";
            }
        }
        // we set javascripts
        if ($javascript instanceof \Doctrine\ORM\PersistentCollection){
            foreach($javascript as $s){
                $source     .= "{% javascript '".$s->getUrl()."' %} \n";
            }
        }
        //$source     .= "{% set meta_title = title_page(app.request.locale,'{$title}') %} \n";
        $source     .= "{% block global_title %}";
        $source     .= "{{ parent() }} \n";
        $source     .= "{{ '{$meta_title}'|striptags }} \n";
        $source     .= "{% endblock %} \n";
        $source     .= "{% set global_local_language = '".$this->language."' %} \n";
        $source     .= " \n";
        $source     .= "{% block global_meta %} \n";
        $source     .= "    {$meta_page}";
        //$source     .= "    {{ metas_page(app.request.locale, {'description':\"{$description}\",'keywords':\"{$keywords}\",'title':\"{$meta_title}\"})|raw }} \n";
        $source     .= "{{ parent() }}    \n";
        $source     .= "{% endblock %} \n";
        // we set all widgets of all blocks
        if (isset($this->blocks[$id]) && !empty($this->blocks[$id])) {
            $all_blocks = $this->blocks[$id];
            foreach ($all_blocks as $block) {
                // if the block is not disabled.                
                if ($block->getEnabled()) {
                    $source     .= "{% block ".$block->getName()." %} \n";
                    $source     .= "{{ parent() }}    \n";
                    $source     .= "<orchestra id=\"block__".$block->getId()."\" data-id=\"".$block->getId()."\" data-name=\"".$this->container->get('translator')->trans($block->getName())."\" style=\"display:block\"> \n";
                    // we set all widget of the block
                    if (isset($this->widgets[$id][$block->getId()]) && !empty($this->widgets[$id][$block->getId()])){
                        $all_widgets      = $this->widgets[$id][$block->getId()];
                        $widget_position = array();
                        foreach ($all_widgets as $widget) {
                            if ($widget->getEnabled()) {
                                if (isset($this->widgets[$id][$block->getId()][$widget->getId()]) && !empty($this->widgets[$id][$block->getId()][$widget->getId()])){
                                    // we get the widget manager
                                    $widgetManager      = $this->getWidgetManager();
                                    // we set the result
                                    $widgetManager->setCurrentWidget($this->widgets[$id][$block->getId()][$widget->getId()]);
                                    // we initialize js and css script of the widget
                                    $widgetManager->setScript();
                                    // we initialize init of the widget
                                    $widgetManager->setInit();                
                                    if ($widget->getPosition() && ($widget->getPosition() != 0)){
                                        $pos = $widget->getPosition();
                                        // we return the render (cache or not)
                                        $widget_position[ $pos ]     = "<orchestra id=\"widget__".$widget->getId()."\" data-id=\"".$widget->getId()."\" style=\"display:block\"> \n";
                                        $widget_position[ $pos ]     .= $widgetManager->render($this->language). " \n";
                                        $widget_position[ $pos ]     .= "</orchestra> \n";
                                    } else {
                                        // we return the render (cache or not)
                                        $widget_position[]              = "<orchestra id=\"widget__".$widget->getId()."\" data-id=\"".$widget->getId()."\" > \n";
                                        $widget_position[]             .= $widgetManager->render($this->language) . " \n";
                                        $widget_position[]             .= "</orchestra> \n";
                                    } 
                                    // we set the js and css scripts.
                                    $container  = strtoupper($widget->getPlugin());
                                    $this->script['js']        = array_merge($this->script['js'], $widgetManager->getScript('js', 'array'));
                                    $this->script['css']    = array_merge($this->script['css'], $widgetManager->getScript('css', 'array'));
                                    $this->script['init']    = array_merge($this->script['init'], $widgetManager->getScript('init', 'array'));
                                }
                            }
                        }
                        ksort($widget_position);
                        $source        .= implode(" \n", $widget_position);
                    }
                    $source     .= " </orchestra> \n";
                    $source     .= " {% endblock %} \n";
                }
            }            
        }
        // we set the js script of the widget
        $source     .= "{% block global_script_js %} \n";
        $source        .= " {{ parent() }} \n"; 
        $source     .= " <script type=\"text/javascript\"> \n";
        $source     .= " //<![CDATA[ \n";
        $source     .= $this->getScript('js', 'implode') . " \n";
        $source     .= " //]]> \n";
        $source     .= " </script> \n";
        $source     .= "{% endblock %} \n";
        // we set the css script of the widget
        $source     .= "{% block global_script_css %} \n";
        $source        .= " {{ parent() }} \n";
        $source     .= " <style type=\"txt/css\"> \n<!-- \n";
        $source     .= $this->getScript('css', 'implode') . " \n";
        $source     .= " \n--> \n</style> \n";
        $source     .= "{% endblock %} \n";
        // we set the widget script of the ajax render
        $is_render_service_with_ajax = $this->container->getParameter('pi_app_admin.page.widget.render_service_with_ajax');
        if($is_render_service_with_ajax) {
            $source     .= "{% block global_script_divers_footer %} \n";
            $source     .= " {{ parent() }} \n";
            $source     .= "{{ obfuscateLinkJS('ajax','hiddenLinkWidget')|raw }}\n";
            $source     .= "{% endblock %} \n";
        }        
        // we set all initWidget
        $source        = $this->getScript('init', 'implode') . "\n" . $source;
        
        //print_r($source);
        //print_r("<br /><br /><br />");
        //exit;
        
        return $source;
    }
    
	/**
	 * Returns the render ESI source of a widget.
	 *
	 * @param string $serviceName
	 * @param string $method
	 * @param string $id
	 * @param string $lang
	 * @param array  $params
	 * @param array  $options
	 * @param mixed  $response
	 * @return string
	 * @access    public
	 *
	 * @author Etienne de Longeaux <etienne_delongeaux@hotmail.com>
	 * @since 2012-01-31
	 */
	public function renderESISource($serviceName, $method, $id, $lang = '', $params = null, $options = null, Response $response = null)
	{
	    // we set the langue
	    if (empty($lang))    $lang = $this->language;
        // we initialize
		$this->initializeRequest($lang, $options);
		// we set the result widget
		$result = $this->container->get($serviceName)->$method($id, $lang, $params);
		// set response
		if (is_null($response)) {
			$response = new Response($result);
		} else {
			$response->setContent($result);
		}
		// Allows proxies to cache the same content for different visitors.
		if (isset($options['public']) && $options['public']) {
			$response->setPublic();
		}
		if (isset($options['lifetime']) && $options['lifetime']) {
			$response->setSharedMaxAge($options['lifetime']);
			$response->setMaxAge($options['lifetime']);
		}
		// Returns a 304 "not modified" status, when the template has not changed since last visit.
		if (
		    isset($options['cacheable']) && $options['cacheable']
		    &&
		    isset($options['update']) && $options['update']
		) {
			$response->setLastModified(date('yyyy-MM-dd H:i:s', $options['update']));
		} else {
			$response->setLastModified(new \DateTime());
		}
		// set header tags.
		$is_force_private_response           = $this->container->getParameter("pi_app_admin.page.esi.force_private_response_for_all");
		$is_force_private_response_with_auth = $this->container->getParameter("pi_app_admin.page.esi.force_private_response_only_with_authentication");
		if (
		    $is_force_private_response
		    ||
		    ($this->isUsernamePasswordToken() && $is_force_private_response_with_auth)
		    ||
		    ( isset($options['lifetime']) && ($options['lifetime'] == 0) )
		) {
			$response->headers->set('Pragma', "no-cache");
			$response->headers->set('Cache-control', "private");
			$response->setSharedMaxAge(0);
			$response->setMaxAge(0);
		}
	
		return $response;
	}	
	
	/**
	 * Initialize the request with a new uri.
	 *
	 * @param $options    ['REQUEST_URI', 'REDIRECT_URL']
	 * @return void
	 * @access public
	 *
	 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
	 * @since 2012-02-16
	 */
	public function initializeRequest($lang = '', array $options = array())
	{
	    // we set the langue
	    if (empty($lang))    $lang = $this->language;
		// we duplicate the current request
		$clone_request = $this->container->get('request')->duplicate();
		// we modify the header request
		if (isset($options['REQUEST_URI']) && !empty($options['REQUEST_URI'])) {
			$clone_request->server->set('REQUEST_URI', $options['REQUEST_URI']);
			$_SERVER['REQUEST_URI'] = $options['REQUEST_URI'];
		}
		if (isset($options['REDIRECT_URL']) && !empty($options['REDIRECT_URL'])) {
			$clone_request->server->set('REDIRECT_URL', $options['REDIRECT_URL']);
			$_SERVER['REDIRECT_URL'] = $options['REDIRECT_URL'];
		}
		// we initialize the request with new values.
		$query      = $clone_request->query->all();
		$request    = $clone_request->request->all();
		$attributes = $clone_request->attributes->all();
		$cookies    = $clone_request->cookies->all();
		$files      = $clone_request->files->all();
		$server     = $clone_request->server->all();
		$this->container->get('request')->initialize($query, $request, $attributes, $cookies, $files, $server);
		// we get the _route value of the new uri
		$match = $this->container->get('bootstrap.RouteTranslator.factory')->getLocaleRoute($lang, array('result'=>'match') );
// 		if ($match && is_array($match)) {
// 			foreach($match as $k => $v) {
// 				$_GET[$k] = $v;
// 				$this->container->get('request')->query->set($k, $v);
// 				$this->container->get('request')->attributes->set($k, $v);
// 			}
// 		}
		// we set the _route value
		$this->container->get('request')->query->set('_route', $match['_route']);
		$this->container->get('request')->attributes->set('_route', $match['_route']);
	} 
    
    /**
     * Sets and return a page by id.
     *
     * @param int    $idPage
     *
     * @return void
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-16
     */
    public function setPageById($idPage)
    {
        $page = $this->getRepository('Page')->find($idPage);        
        if ($page instanceof Page) {
            // we set the result
            $this->setCurrentPage($page);
            // we set the page.
            $this->setPage($page);
            // we return the setting page.
            return $page;            
        } else {
            return false;
        }
    }    
    
    /**
     * Sets and return a page by url and slug.
     *
     * @param string    $url    url value of a page
     * @param string    $slug    slug value of a translation of a page
     * @param bool        $isSetPage
     * 
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function setPageByParams($url, $slug, $isSetPage = false) 
    {
        // Get corresponding page
        if (!$slug && !$url) {
            $page = $this->getRepository('page')->getHomepage();
        } else {
            $slug = explode('/', $slug);
            $slug = $slug[count($slug) - 1];
            $page = $this->getRepository('page')->getPageByUrlAndSlug($url, $slug);
        }        
        if ($page instanceof Page) {
            // we set the result
            $this->setCurrentPage($page);
            // we set the page.
            if ($isSetPage) {
                $this->setPage($page);
            }
            // we return the setting page.
            return $page;
        } else {
            return false;
        }
    }

    /**
     * Sets and return a page by a route name.
     *
     * @param string    $route        route page
     * @param bool        $isSetPage
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function setPageByRoute($route = '', $isSetPage = false)
    {
        // Get corresponding page
        if (!$route || empty($route)) {
            $page = $this->getRepository('page')->getHomepage();
        } else {
            $page = $this->getRepository('page')->findOneBy(array('route_name' => $route));
        }
        if ($page instanceof Page) {
            // we set the result
            $this->setCurrentPage($page);
            // we set the page.
            if ($isSetPage) {
                $this->setPage($page);
            }
            // we return the setting page.
            return $page;
        } else {
            return false;
        }
    }  

  	/**
  	 * Redirect to the url by his route name.
  	 *
  	 * @param string $route_name        route name of a page
  	 * @param string $lang
  	 * @return string    content page
  	 * @access    public
  	 *
  	 * @author Etienne de Longeaux <etienne_delongeaux@hotmail.com>
  	 * @since 2012-06-11
  	 */
  	public function redirectPage($route_name = 'error_404')
  	{
        $url_redirection = $this->container->get('bootstrap.RouteTranslator.factory')->getRoute($route_name);
        header('Location: '. $url_redirection);
        exit;
  	}  	

    /**
     * Sets a page and construct all it information.
     *
     * @param \PiApp\AdminBundle\Entity\Page $page
     *
     * @return void
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-31
     */
    private function setPage(Page $page)
    {
        $id = $page->getId();
        if (!$this->getPageById($id)){
            // we register all translations page linked to one page.
            $this->setTranslations($page);
            // we register all blocks linked to one page.
            $this->setBlocks($page);    
            // we register all widgets linked to one page
            $this->setWidgets($page);        
            // we register the page
            $this->pages[$id] = $page;
        }
    }    
    
    /**
     * Sets all the related translations linked to one page.
     *
     * @param \PiApp\AdminBundle\Entity\Page $page
     *
     * @return void
     * @access private
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    private function setTranslations(Page $page, $locale = false)
    {
        if (!isset($this->translations[$page->getId()]) || empty($this->translations[$page->getId()])) {            
            if (!$locale) {
                // records all translations
                $all_translations = $page->getTranslations();
                foreach ($all_translations as $translation) {
                    $this->translations[$page->getId()][$translation->getLangCode()->getId()] = $translation;
                }
            } else {
                $translationPage = $this->getRepository('translationPage')->findOneBy(array('page' => $page->getId(), 'langCode'=>$locale));
                if ($translationPage instanceof \PiApp\AdminBundle\Entity\TranslationPage) {
                    $this->translations[$page->getId()][$locale] = $translationPage;
                }
            }
        }
    }    
    
    /**
     * Sets all the related block linked to one page.
     *
     * @param \PiApp\AdminBundle\Entity\Page $page
     * 
     * @return void
     * @access private
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    private function setBlocks(Page $page)
    {
        if (!isset($this->blocks[$page->getId()]) || empty($this->blocks[$page->getId()])) {
            $all_blocks = $page->getBlocks();
            // records all blocks
            foreach ($all_blocks as $block) {
                $this->blocks[$page->getId()][$block->getId()] = $block;
            }
        }
    }
    
    /**
     * Sets all the related block linked to one page.
     *
     * @param \PiApp\AdminBundle\Entity\Page $page
     *
     * @return void
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-31
     */
    private function setWidgets(Page $page)
    {
        if (isset($this->blocks[$page->getId()]) && !empty($this->blocks[$page->getId()])) {
            $all_blocks = $this->blocks[$page->getId()];
            // records all widgets
            foreach ($all_blocks as $block) {
                $all_widgets = $block->getWidgets();
                foreach ($all_widgets as $widget) {
                    $this->widgets[$page->getId()][$block->getId()][$widget->getId()] = $widget;
                }            
            }
        }
    }    
    
    /**
     * Sets the response to one page.
     * 
     * @param \PiApp\AdminBundle\Entity\Page $page
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-31
     */
    private function setResponse($page, Response $response)
    {
        $this->responses['page'][$page->getId()] = $response;
    }    
    
    /**
     * Sets the Widget manager service.
     *
     * @return void
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-31
     */
    private function setWidgetManager()
    {
        $this->widgetManager = $this->container->get('pi_app_admin.manager.widget');
    }
    
    /**
     * Gets the Widget manager service
     *
     * @return \PiApp\AdminBundle\Manager\PiWidgetManager
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-31
     */
    private function getWidgetManager()
    {
        if (empty($this->widgetManager)) {
            $this->setWidgetManager();
        }
    
        return $this->widgetManager;
    }    
    
    /**
     * It redirects to the public url home page.
     *
     * @return void
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-14
     */
    private function redirectHomePublicPage(){
        // It tries to redirect to the original page.
        // probleme avec les esi => pas de valeur retourné
        $url = $this->container->get('request')->headers->get('referer');
        if (empty($url)) {
            $url = $this->container->get('router')->generate('home_page');
        }
        
        return new RedirectResponse($url);
    }    
    
    /**
     * Return the ChildrenHierarchy result of the rubrique entity.
     *
     * @return string
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-29
     */
    public function getChildrenHierarchyRub()
    {
        $_em = $this->container->get('pi_app_admin.repository');    
        $options = array(
                'decorate' => true,
                'rootOpen' => "\n <ul> \n",
                'rootClose' => "\n </ul> \n",
                'childOpen' => "    <li class='collapsed' > \n",        // 'childOpen' => "    <li class='collapsed' > \n",
                'childClose' => "    </li> \n",
                'nodeDecorator' => function($node) {
                    return  '<a data-rub="'.$node['id'].'" >'.$node["titre"].'</a><p class="pi_tree_desc">'.$node["descriptif"]."</p>";
                }
        );
        $htmlTree = $_em->getRepository('Rubrique')->childrenHierarchy(
                null, /* starting from root nodes */
                false, /* load all children, not only direct */
                $options
        );
    
        return $htmlTree;
    }
    
    /**
     * Modify the tree result with the pages blocks.
     *
     * @return string
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-29
     */
    public function setTreeWithPages($htmlTree)
    {
        if (empty($htmlTree)) {
            return $htmlTree;
        }    
        $htmlTree = $this->container->get('pi_app_admin.string_manager')->trimUltime($htmlTree);
        if (preg_match_all('#<a data-rub="(?P<id_rubrique>(.*))" >(?P<titre>(.*))</a><p class="pi_tree_desc">(?P<descriptif>(.*))</p>#sU', $htmlTree, $matches_rubs)){
            foreach ($matches_rubs['id_rubrique'] as $key => $idRubrique) {
                $result_simple   = preg_split('#<a data-rub="'.$idRubrique.'" >(.*)</a><p class="pi_tree_desc">(.*)</p>#sU', $htmlTree);
                $result_multiple = preg_split('#<a data-rub="'.$idRubrique.'" >(.*)</a><p class="pi_tree_desc">(.*)</p>(.*)<ul>#sU', $htmlTree);    
                if (count($result_simple) == 2) {
                    $allRubriquePages = $this->getPagesByRub($idRubrique);
                    if (!empty($allRubriquePages))
                        $htmlTree = $result_simple[0]
                        . '<a data-rub="'.$idRubrique.'" >'.$matches_rubs['titre'][$key].'</a><p class="pi_tree_desc">'.$matches_rubs['descriptif'][$key].'</p>'
                        . '<ul>'
                        . $allRubriquePages
                        . '</ul>'
                        . $result_simple[1];
                }
                if (count($result_multiple) == 2) {
                    $allRubriquePages = $this->getPagesByRub($idRubrique);
                    if (!empty($allRubriquePages))
                        $htmlTree = $result_multiple[0]
                        . '<a data-rub="'.$idRubrique.'" >'.$matches_rubs['titre'][$key].'</a><p class="pi_tree_desc">'.$matches_rubs['descriptif'][$key].'</p>'
                        . '<ul>'
                        . $allRubriquePages
                        . $result_multiple[1];
                }
            }
        }
    
        return $htmlTree;
    }
    
    /**
     * Sets the home page.
     *
     * @return string
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-29
     */
    public function setHomePage($htmlTree)
    {
        $pages_content    = "";
        $page             =  $this->container->get('pi_app_admin.repository')->getRepository('Page')->getHomePage();    
        if ($page instanceof Page){
            if ( !$page->getTranslations()->isEmpty() ) {
                $locales = array();
                $pages_content .= "<li><p>Home Page ".$page->getId()."</p><a href='#'>url : ".$page->getUrl()."</a><p></p><ul>";
                foreach ($page->getTranslations() as $key=>$translationPage) {
                    if ($translationPage instanceof TranslationPage){
                        $local = $translationPage->getLangCode()->getId();
                        try {
                            $route = $this->container->get('router')->generate( $page->getRouteName(), array('locale' => $local) );
                        } catch (\Exception $e) {
                            $route = $this->container->get('router')->generate( $page->getRouteName());
                        }
                        $pages_content .= "<li>";
                        $pages_content .= "<p>local ".$local."</p><a href='".$route."'>slug : ".$translationPage->getSlug()."</a><p class='pi_tree_title'>".$translationPage->getTitre()."</p><p class='pi_tree_desc'>".$translationPage->getDescriptif ()."</p>";
                        $pages_content .= "</li>";
                    }
                }
                $pages_content .= "</ul></li>";
            }
        }
        $pages_content      = preg_replace('#<ul>#sU', '<ul>'.$pages_content, $htmlTree, 1);
        
        return $pages_content;
    }
    
    /**
     * Gets all page of a rubrique.
     *
     * @return string
     * @access private
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-28
     */
    private function getPagesByRub($idRubrique)
    {
        $pages_content   = "";
        $pagesByRubrique =  $this->container->get('pi_app_admin.repository')->getRepository('Page')->getAllPageByRubriqueId($idRubrique)->getQuery()->getResult();
        if (is_array($pagesByRubrique)) {
            foreach($pagesByRubrique as $key => $page) {
                if ($page instanceof Page) {
                    if ( !$page->getTranslations()->isEmpty() ) {
                        $locales = array();
                        $pages_content .= "<li><p>Page ".$page->getId()."</p><a href='#'>url : ".$page->getUrl()."</a><p></p><ul>";
                        foreach ($page->getTranslations() as $key=>$translationPage) {
                            if ($translationPage instanceof TranslationPage) {
                                $local = $translationPage->getLangCode()->getId();
                                try {
                                    $route = $this->container->get('router')->generate( $page->getRouteName(), array('locale' => $local) );
                                } catch (\Exception $e) {
                                    try {
                                        $route = $this->container->get('router')->generate( $page->getRouteName() );
                                    } catch (\Exception $e) {
                                        $route = "";
                                    }
                                }
                                $pages_content .= "<li class='css-transform-rotate dhtmlgoodies_sheet.gif' >";
                                $pages_content .= "<p>local ".$local."</p><a href='".$route."'>slug : ".$translationPage->getSlug()."</a><p class='pi_tree_title'>".$translationPage->getTitre()."</p><p class='pi_tree_desc'>".$translationPage->getDescriptif ()."</p>";
                                $pages_content .= "</li>";
                            }
                        } // end foreach
                        $pages_content .= "</ul></li>";
                    }
                }
            } // end foreach
        }
    
        return $pages_content;
    }    
    
    /**
     * Add node numeber in the <li>.
     *
     * @return string
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-02-29
     */
    public function setNode($htmlTree)
    {
        if (empty($htmlTree)) {
            return $htmlTree;
        }
        //print_r($htmlTree);
        $htmlTree             = $this->container->get('pi_app_admin.string_manager')->trimUltime($htmlTree);
        $matches_balise_rub    = preg_split('#<li>(?P<num>(.*))#sU', $htmlTree);
        $max_key            = 1;
        if ($matches_balise_rub) {
            //print_r($matches_balise_il);
            $htmlTree = '';
            $max_key = count($matches_balise_rub)-1;
            foreach ($matches_balise_rub as $key => $value) {
                if ($max_key != $key) {
                    $htmlTree .= $value . '<li id="node'.($key+1).'">';
                } else {
                    $htmlTree .= $value;
                }
            }
        }
        $matches_balise_page     = preg_split("#<li class='dhtmlgoodies_sheet.gif'>(?P<num>(.*))#sU", $htmlTree);
        $max_key                = 1;
        if ($matches_balise_page) {
            //print_r($matches_balise_il);
            $htmlTree = '';
            $max_key = count($matches_balise_page)-1;
            foreach($matches_balise_page as $key => $value) {
                if ($max_key != $key) {
                    $htmlTree .= $value . '<li id="node'.($key+1).'" class=\'dhtmlgoodies_sheet.gif\'>';
                } else {
                    $htmlTree .= $value;
                }
            }
        }        
        //print_r($htmlTree);exit;
    
        return $htmlTree;
    }

    /**
     * Refresh the cache of the tree Chart page.
     *
     * @return string
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-05-11
     */
    public function cacheTreeChartPageRefresh()
    {
        // we manage the "tree chart page"
        $params_treechart = array();
        $params_treechart['action']  = "renderByClick";
        $params_treechart['id']          = ".org-chart-page";
        $params_treechart['menu']      = "page";
        // we sort an array by key in reverse order
        krsort($params_treechart);
        // we create de Etag cache
        $params_treechart      = json_encode($params_treechart);
        $params_treechart     = str_replace(':', '#', $params_treechart);
        // we refresh all caches 
        $all_lang    = $this->getRepository('Langue')->findByEnabled(true);
        foreach($all_lang as $key => $lang) {
            $id_lang = $lang->getId();
            $Etag_treechart = "organigram:Rubrique~org-chart-page:$id_lang:$params_treechart";
            // we refresh the cache
            $this->cacheRefreshByname($Etag_treechart);
        }
    }    
    
    /**
     * Refresh the cache of all elements of a page (TranslationPages, widgets, translationWidgets)
     *
     * @return string
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-04-03
     */    
    public function cacheRefresh()
    {
        // we refresh the cache of the tree Chart page.
        $this->cacheTreeChartPageRefresh();
        // we get the current page.
        $page = $this->getCurrentPage();
        if (!is_null($page) && !is_null($this->translations[$page->getId()])) {
            foreach ($this->translations[$page->getId()] as $translation) {
                // we get the lang page
                $lang_page = $translation->getLangCode()->getId();
                // we create the cache name
                $referer_url = $this->container->get('bootstrap.RouteTranslator.factory')->getRefererRoute($lang_page, null, true);
                $referer_url = str_replace($this->container->get('request')->getUriForPath(''), '', $referer_url);
                $name_page = $this->createEtag('page', $page->getId(), $lang_page, array('page-url'=>$referer_url));
                $name_page    = str_replace('//', '/', $name_page);
                if (isset($this->widgets[$page->getId()]) && is_array($this->widgets[$page->getId()])) {
                    foreach ($this->widgets[$page->getId()] as $key_block=>$widgets) {
                        if (isset($this->widgets[$page->getId()][$key_block]) && is_array($this->widgets[$page->getId()][$key_block])) {
                            foreach ($this->widgets[$page->getId()][$key_block] as $key_widget => $widget) {
//                                 print_r($this->container->get('request')->getLocale());
//                                 print_r(' - id : ' . $widget->getId());
//                                 print_r(' - plugin : ' . $widget->getPlugin());
//                                 print_r(' - action : ' . $widget->getAction());
//                                 print_r('<br />');                                
                                // we create the cache name of the widget
                                $Etag_widget  = 'widget:'.$widget->getId().':'.$lang_page;
                                // we refesh only if the widget is in cash.
                                $this->cacheRefreshByname($Etag_widget);
                                //print_r('<br />');print_r('<br />');
                                
                                $params_transwidget = json_encode(array('widget-id'=>$widget->getId()), JSON_UNESCAPED_UNICODE);
                                // we manage the "transwidget"
                                $widget_translations = $this->getWidgetManager()->setWidgetTranslations($widget);
                                if (is_array($widget_translations)) {
                                    foreach ($widget_translations as $translang => $translationWidget) {
                                        // we create the cache name of the transwidget
                                        $Etag_transwidget  = 'transwidget:'.$translationWidget->getId().':'.$translang.':'.$params_transwidget;
                                        // we refresh the cache of the transwidget
                                        $this->cacheRefreshByname($Etag_transwidget);
                                    }
                                }
                                // If the widget is a "content snippet"
                                if ( ($widget->getPlugin() == 'content') && ($widget->getAction() == 'snippet') ) {
                                    $xmlConfig    = $widget->getConfigXml();
                                    // if the configXml field of the widget is configured correctly.
                                    try {
                                        $xmlConfig    = new \Zend_Config_Xml($xmlConfig);
                                        if ($xmlConfig->widgets->get('content')) {
                                            $id_snippet    = $xmlConfig->widgets->content->id;
                                            // we create the cache name of the snippet
                                            $Etag_snippet  = 'transwidget:'.$id_snippet.':'.$lang_page.':'.$params_transwidget;
                                            // we refresh the cache of the snippet
                                            $this->cacheRefreshByname($Etag_snippet);
                                        }
                                    } catch (\Exception $e) {
                                    }
                                }
                                
                            $path_json_file = $this->createJsonFileName('widget', $widget->getId(), $lang_page);
                    		if (file_exists($path_json_file)) {
                    		    $info = explode('|', file_get_contents($path_json_file));
                    		    $this->cacheRefreshByname($info[1]);
                    		    //print_r($info[1]);
                    		    //print_r('<br />');print_r('<br />');
                    		    
                    		}
                                
                                
//                                 // If the widget is a tree a "jqext"
//                                 if ( ($widget->getPlugin() == 'content') && ($widget->getAction() == 'jqext') ) {
//                                     $xmlConfig = $widget->getConfigXml();
//                                     // if the configXml field of the widget is configured correctly.
//                                     try {
//                                         $xmlConfig = new \Zend_Config_Xml($xmlConfig);
//                                         if ($xmlConfig->widgets->get('content') && $xmlConfig->widgets->content->get('controller') && $xmlConfig->widgets->content->get('params')) {
//                                             $controller = $xmlConfig->widgets->content->controller;
//                                             $params     = $xmlConfig->widgets->content->params->toArray();
//                                             if ($xmlConfig->widgets->content->params->get('cachable')) {
//                                                 $params['cachable'] = $xmlConfig->widgets->content->params->cachable;
//                                             } else {
//                                                 $params['cachable'] = 'true';
//                                             }    
//                                             $params['widget-id']        = $widget->getId();
//                                             $params['widget-lifetime']  = $widget->getLifetime();
//                                             $params['widget-cacheable'] = strval($widget->getCacheable());
//                                             $params['widget-update']    = $widget->getUpdatedAt()->getTimestamp();
//                                             $params['widget-public']    = strval($widget->getPublic());
//                                             $values     = explode(':', $controller);
//                                             $JQcontainer= strtoupper($values[0]);
//                                             $JQservice  = strtolower($values[1]);                                
//                                             // we sort an array by key in reverse order
//                                             $this->container->get('pi_app_admin.array_manager')->recursive_method($params, 'krsort');
//                                             // we create de Etag cache
//                                             //$params     = $this->container->get('pi_app_admin.string_manager')->json_encodeDecToUTF8($params);
//                                             $params = $this->paramsEncode($params);
//                                             $id     = $this->_Encode("$JQcontainer~$JQservice", false);
//                                             $Etag_jqext = $widget->getAction() . ":$id:$lang_page:$params";                                
//                                         	// we refesh only if the widget is in cash.
//                                            	$this->cacheRefreshByname($Etag_jqext);
//                                         }
//                                     } catch (\Exception $e) {
//                                     }
//                                 }                           
//                                 // If the widget is a "gedmo snippet"
//                                 if ( ($widget->getPlugin() == 'gedmo') && ($widget->getAction() == 'snippet') ) {
//                                     $xmlConfig  = $widget->getConfigXml();
//                                     $new_widget = null;                                    
//                                     // if the configXml field of the widget is configured correctly.
//                                     try {
//                                         $xmlConfig    = new \Zend_Config_Xml($xmlConfig);
//                                         if ($xmlConfig->widgets->get('gedmo')) {
//                                             $id_snippet   = $xmlConfig->widgets->gedmo->id;
//                                             // we create the cache name of the snippet
//                                             $Etag_snippet = 'widget:'.$id_snippet.':'.$lang_page;                                            
//                                             // we refresh the cache of the snippet
//                                             $this->cacheRefreshByname($Etag_snippet);                                            
//                                             // we allow to refresh the cache of the widget of the snippet
//                                             $new_widget   = $this->getWidgetById($id_snippet);
//                                         }
//                                     } catch (\Exception $e) {
//                                     }                                    
//                                     if (!is_null($new_widget) && ($new_widget instanceof Widget) ) {
//                                         $widget = $new_widget;
//                                     }
//                                 }        

// //                                 print_r($this->container->get('request')->getLocale());
// //                                 print_r(' - id : ' . $widget->getId());
// //                                 print_r(' - plugin : ' . $widget->getPlugin());
// //                                 print_r(' - action : ' . $widget->getAction());
// //                                 print_r('<br />');                                
                                
//                                 // If the widget is a search lucene
//                                 if ( ($widget->getPlugin() == 'search') && ($widget->getAction() == 'lucene') ) {
//                                 	$xmlConfig            = $widget->getConfigXml();
//                                 	// if the configXml field of the widget is configured correctly.
//                                 	try {
//                                 		$xmlConfig    = new \Zend_Config_Xml($xmlConfig);
//                                 		if ($xmlConfig->widgets->get('search') && $xmlConfig->widgets->search->get('controller') && $xmlConfig->widgets->search->get('params')  ) {
//                                 		    $controller    = $xmlConfig->widgets->search->controller;
//                                             if ($xmlConfig->widgets->search->params->get('cachable')) {
//                                                 $params['cachable'] = $xmlConfig->widgets->search->params->cachable;
//                                             } else {
//                                                 $params['cachable'] = 'true';
//                                             }
//                                             if ($xmlConfig->widgets->search->params->get('template')) {
//                                                 $params['template'] = $xmlConfig->widgets->search->params->template;
//                                             } else {
//                                                 $params['template'] = "";
//                                             }
//                                             if ($xmlConfig->widgets->search->params->get('MaxResults')) {
//                                                 $params['MaxResults'] = $xmlConfig->widgets->search->params->MaxResults;
//                                             } else {
//                                                 $params['MaxResults'] = 0;            
//                                             }
//                                             $params['widget-id']        = $widget->getId();
//                                             $params['widget-lifetime']  = $widget->getLifetime();
//                                             $params['widget-cacheable'] = strval($widget->getCacheable());
//                                             $params['widget-update']    = $widget->getUpdatedAt()->getTimestamp();
//                                             $params['widget-public']    = strval($widget->getPublic());
//                                 		    if ($xmlConfig->widgets->search->params->get('lucene')) {            
//                                                    $params      = array_merge($params, $xmlConfig->widgets->search->params->lucene->toArray());            
//                                                    $values      = explode(':', $controller);
//                                                    $JQcontainer = strtoupper($values[0]);
//                                                    $JQservice   = strtolower($values[1]);
//                                                    // we sort an array by key in reverse order
//                                                    $this->container->get('pi_app_admin.array_manager')->recursive_method($params, 'krsort');
//                                                    // we create de Etag cache
//                                                    //$params            = $this->container->get('pi_app_admin.string_manager')->json_encodeDecToUTF8($params);
//                                                    $params  = $this->paramsEncode($params);
//                                                    $id      = $this->_Encode("$JQcontainer~$JQservice", false);
//                                                    $Etag_searchlucene = $widget->getAction() . ":$id:$lang_page:$params";
//                                                    // we refesh only if the widget is in cash.
//                                                    $this->cacheRefreshByname($Etag_searchlucene);
//                                             } 
//                                 		}
//                                 	} catch (\Exception $e) {
//                                     }
//                                 }
                                                                
//                                 // If the widget is a "listener"
//                                 if ( ($widget->getPlugin() == 'gedmo') && ($widget->getAction() == 'listener') ) {
//                                     $xmlConfig            = $widget->getConfigXml();
//                                     // if the configXml field of the widget is configured correctly.
//                                     try {
//                                         $xmlConfig    = new \Zend_Config_Xml($xmlConfig);
//                                         if ($xmlConfig->widgets->get('gedmo') && $xmlConfig->widgets->gedmo->get('controller') && $xmlConfig->widgets->gedmo->get('params')) {
//                                             $controller    = $xmlConfig->widgets->gedmo->controller;
//                                             $params        = $xmlConfig->widgets->gedmo->params->toArray();
//                                             //
//                                             if ($xmlConfig->widgets->gedmo->params->get('cachable')) {
//                                                 $params['cachable'] = $xmlConfig->widgets->gedmo->params->cachable;
//                                             } else {
//                                                 $params['cachable'] = 'true';
//                                             }
//                                             $params['widget-id']        = $widget->getId();
//                                             $params['widget-lifetime']  = $widget->getLifetime();
//                                             $params['widget-cacheable'] = strval($widget->getCacheable());
//                                             $params['widget-update']    = $widget->getUpdatedAt()->getTimestamp();
//                                             $params['widget-public']    = strval($widget->getPublic());
//                                             // we sort an array by key in reverse order
//                                             $this->container->get('pi_app_admin.array_manager')->recursive_method($params, 'krsort');
//                                             // we create de Etag cache
//                                             //$params        = $this->container->get('pi_app_admin.string_manager')->json_encodeDecToUTF8($params);
//                                             $params = $this->paramsEncode($params);
//                                             $id     = $this->_Encode($controller, false);
//                                             $Etag_listener = $widget->getAction() . ":$id:$lang_page:$params";
//                                             //print_r($Etag_listener);	                                            
//                                             //print_r("<br/><br/>");
//                                             // we refesh only if the widget is in cash.
//                                            	$this->cacheRefreshByname($Etag_listener);
//                                         }
//                                     } catch (\Exception $e) {
//                                     }                        
//                                 }                            
//                                 // If the widget is a "tree"
//                                 if ( ($widget->getPlugin() == 'gedmo') && (($widget->getAction() == 'navigation') || ($widget->getAction() == 'organigram')) ) {
//                                     $xmlConfig            = $widget->getConfigXml();
//                                     // if the configXml field of the widget is configured correctly.
//                                     try {
//                                         $xmlConfig    = new \Zend_Config_Xml($xmlConfig);
//                                         if ($xmlConfig->widgets->get('gedmo') && $xmlConfig->widgets->gedmo->get('controller') && $xmlConfig->widgets->gedmo->get('params')) {
//                                             $values     = explode(':', $xmlConfig->widgets->gedmo->controller);
//                                             $entity     = $values[0] . ':' . $values[1];
//                                             $method     = strtolower($values[2]);
//                                             $params        = array();
//                                             //                                   
//                                             if ($xmlConfig->widgets->gedmo->params->get('category')) {
//                                                 $category = $xmlConfig->widgets->gedmo->params->category;
//                                             } else {
//                                                 $category = "";
//                                             }
//                                             if ($xmlConfig->widgets->gedmo->params->get('node')) {
//                                                 $params['node'] = $xmlConfig->widgets->gedmo->params->node;
//                                             } else {
//                                                 $params['node'] = "";
//                                             }
//                                             if ($xmlConfig->widgets->gedmo->params->get('enabledonly')) {
//                                                 $params['enabledonly'] = $xmlConfig->widgets->gedmo->params->enabledonly;
//                                             } else {
//                                                 $params['enabledonly'] = "true";
//                                             }
//                                             if ($xmlConfig->widgets->gedmo->params->get('cachable')) {
//                                                 $params['cachable'] = $xmlConfig->widgets->gedmo->params->cachable;
//                                             } else {
//                                                 $params['cachable'] = 'true';
//                                             }
//                                             if ($xmlConfig->widgets->gedmo->params->get('template')) {
//                                                 $template = $xmlConfig->widgets->gedmo->params->template;
//                                             } else {
//                                                 $template = "";
//                                             }
//                                             $params['entity']    = $entity;
//                                             $params['category']  = $category;
//                                             $params['template']  = $template;
//                                             $params['widget-id'] = $widget->getId();
//                                             $params['widget-lifetime']  = $widget->getLifetime();
//                                             $params['widget-cacheable'] = strval($widget->getCacheable());
//                                             $params['widget-update']    = $widget->getUpdatedAt()->getTimestamp();
//                                             $params['widget-public']    = strval($widget->getPublic());
//                                             //
//                                             if ($xmlConfig->widgets->gedmo->params->get('navigation')) {                                            
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('separatorClass')) {
//                                                     $params['separatorClass'] = $xmlConfig->widgets->gedmo->params->navigation->separatorClass;
//                                                 } else {
//                                                     $params['separatorClass'] = "";
//                                                 }                                                
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('separatorText')) {
//                                                     $params['separatorText'] = $xmlConfig->widgets->gedmo->params->navigation->separatorText;
//                                                 } else {
//                                                     $params['separatorText'] = "";
//                                                 }
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('separatorFirst')) {
//                                                     $params['separatorFirst'] = $xmlConfig->widgets->gedmo->params->navigation->separatorFirst;
//                                                 } else {
//                                                     $params['separatorFirst'] = "false";
//                                                 }                                                
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('separatorLast')) {
//                                                     $params['separatorLast'] = $xmlConfig->widgets->gedmo->params->navigation->separatorLast;
//                                                 } else {
//                                                     $params['separatorLast'] = "false";
//                                                 }
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('ulClass')) {
//                                                     $params['ulClass'] = $xmlConfig->widgets->gedmo->params->navigation->ulClass;
//                                                 } else {
//                                                     $params['ulClass'] = "";
//                                                 }
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('liClass')) {
//                                                     $params['liClass'] = $xmlConfig->widgets->gedmo->params->navigation->liClass;
//                                                 } else {
//                                                     $params['liClass'] = "";
//                                                 }
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('counter')) {
//                                                     $params['counter'] = $xmlConfig->widgets->gedmo->params->navigation->counter;
//                                                 } else {
//                                                     $params['counter'] = "";
//                                                 }
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('routeActifMenu')) {
//                                                     $params['routeActifMenu'] = $xmlConfig->widgets->gedmo->params->navigation->routeActifMenu->toArray();
//                                                 }
//                                                 if ($xmlConfig->widgets->gedmo->params->navigation->get('lvlActifMenu')) {
//                                                     $params['lvlActifMenu'] = $xmlConfig->widgets->gedmo->params->navigation->lvlActifMenu->toArray();
//                                                 }
//                                             } elseif ($xmlConfig->widgets->gedmo->params->get('organigram')) {                                            
//                                                 if ($xmlConfig->widgets->gedmo->params->organigram->get('params'))
//                                                     $params = array_merge($params, $xmlConfig->widgets->gedmo->params->organigram->params->toArray());
                                            
//                                                 if ($xmlConfig->widgets->gedmo->params->organigram->get('fields') && $xmlConfig->widgets->gedmo->params->organigram->fields->get('field'))
//                                                 {
//                                                     $params['fields'] = $xmlConfig->widgets->gedmo->params->organigram->fields->field->toArray();
//                                                 }
//                                             }                                        
//                                             // we sort an array by key in reverse order
//                                             $this->container->get('pi_app_admin.array_manager')->recursive_method($params, 'krsort');
//                                             // we create de Etag cache
//                                             //$params     = $this->container->get('pi_app_admin.string_manager')->json_encodeDecToUTF8($params);
//                                             $params     = $this->paramsEncode($params);
//                                             $entity     = stripslashes($this->_Encode($entity, false));
//                                             $id         = $this->_Encode("$entity~$method~$category", false);
//                                             $Etag_tree  = $widget->getAction() . ":$id:$lang_page:$params";
//                                             // we refesh only if the widget is in cash.
//                                            	$this->cacheRefreshByname($Etag_tree);
//                                            	//print_r($Etag_tree);
//                                            	//print_r("<br/><br/>");
//                                         }
//                                     } catch (\Exception $e) {
//                                     }
//                                 }                                
//                                 // If the widget is a "slider"
//                                 if ( ($widget->getPlugin() == 'gedmo') && ($widget->getAction() == 'slider') ) {
//                                     $xmlConfig            = $widget->getConfigXml();
//                                     // if the configXml field of the widget is configured correctly.
//                                     try {
//                                         $xmlConfig    = new \Zend_Config_Xml($xmlConfig);
//                                         if ($xmlConfig->widgets->get('gedmo') && $xmlConfig->widgets->gedmo->get('controller') && $xmlConfig->widgets->gedmo->get('params')) {
//                                             $values     = explode(':', $xmlConfig->widgets->gedmo->controller);
//                                             $entity     = $values[0] . ':' . $values[1];
//                                             $method     = strtolower($values[2]);
//                                             $params     = array();
//                                             //                             
//                                             if ($xmlConfig->widgets->gedmo->params->get('category')) {
//                                                 $category = $xmlConfig->widgets->gedmo->params->category;
//                                             } else {
//                                                 $category = "";
//                                             }                                            
//                                             if ($xmlConfig->widgets->gedmo->params->get('template')) {
//                                                 $template = $xmlConfig->widgets->gedmo->params->template;
//                                             } else {
//                                                 $template = "";
//                                             }          
//                                             $params = array();
//                                             if ($xmlConfig->widgets->gedmo->params->get('slider')) {                                        
//                                                 $params = $xmlConfig->widgets->gedmo->params->slider->toArray();
//                                                 $params['entity']    = $entity;
//                                                 $params['category']  = $category;
//                                                 $params['template']  = $template;
//                                                 $params['widget-id'] = $widget->getId();
//                                                 $params['widget-lifetime']  = $widget->getLifetime();
//                                                 $params['widget-cacheable'] = strval($widget->getCacheable());
//                                                 $params['widget-update']    = $widget->getUpdatedAt()->getTimestamp();
//                                                 $params['widget-public']    = strval($widget->getPublic());
// 												//                                                
//                                                 if ($xmlConfig->widgets->gedmo->params->get('cachable')) {
//                                                     $params['cachable'] = $xmlConfig->widgets->gedmo->params->cachable;
//                                                 } else {
//                                                     $params['cachable'] = 'true';
//                                                 }                                           
//                                                 if ($xmlConfig->widgets->gedmo->params->slider->get('params')) {
//                                                     $params['params'] = $xmlConfig->widgets->gedmo->params->slider->params->toArray();
//                                                 }
//                                                 if (!isset($params['action']) || empty($params['action'])) {
//                                                     $params['action']   = 'renderDefault';
//                                                 }
//                                                 if (!isset($params['menu']) || empty($params['menu'])) {
//                                                     $params['menu']     = 'entity';
//                                                 }
//                                             }                                            
//                                             // we sort an array by key in reverse order
//                                             $this->container->get('pi_app_admin.array_manager')->recursive_method($params, 'krsort');
//                                             // we create de Etag cache
//                                             //$params      = $this->container->get('pi_app_admin.string_manager')->json_encodeDecToUTF8($params);
//                                             $params      = $this->paramsEncode($params);
//                                             $entity      = stripslashes($this->_Encode($entity, false));
//                                             $id          = $this->_Encode("$entity~$method~$category", false);
//                                             $Etag_slider = $widget->getAction() . ":$id:$lang_page:$params";
//                                             // we refesh only if the widget is in cash.
//                                            	$this->cacheRefreshByname($Etag_slider);
//                                         }
//                                     } catch (\Exception $e) {
//                                     }                            
//                                 }                      

                            }
                        }
                    }
                }
                // we refesh only if the widget is in cash.
               	$this->cacheRefreshByname($name_page);
               	//print_r('<br />');print_r('<br />');
            }
        }
        //exit;
    }
    
    /**
     * Copy the page with all elements of a page (TranslationPages, widgets, translationWidgets, block)
     * 
     * @param string	locale value.
     * @return string	the new url of the page.
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-04-03
     */
    public function copyPage($locale = '')
    {
    	$em = $this->getContainer()->get('doctrine')->getManager();    	
    	if (empty($locale)) {
    		$locale       = $this->getContainer()->get('request')->getLocale();
    	}
    	// we get the current page.
    	$page = $this->getCurrentPage();
    	$id = $page->getId();
    	if (!is_null($page) && !is_null($this->translations[$id])) {    
    		$eventManager = $em->getEventManager();
    		$eventManager->removeEventListener(
    				array('prePersist'),
    				$this->getContainer()->get('pi_app_admin.prepersist_listener')
    		);
    		$eventManager->removeEventListener(
    				array('postPersist'),
    				$this->getContainer()->get('pi_app_admin.postpersist_listener')
    		);
    		$eventManager->removeEventListener(
    				array('preUpdate'),
    				$this->getContainer()->get('pi_app_admin.preupdate_listener')
    		);
    		$eventManager->removeEventListener(
    				array('postUpdate'),
    				$this->getContainer()->get('pi_app_admin.postupdate_listener')
    		);
			//    		
    		$new_page = clone($page);
    		$new_page->setId(null);
    		// we copy all translations.
    		foreach ($this->translations[$id] as $translation) {
    			$new_translation = clone($translation);
    			$new_translation->setId(null);
    			$new_page->addTranslation($new_translation);
    		}
			// we clone all blocks and all widgets.   		
    		if (isset($this->blocks[$id]) && !empty($this->blocks[$id])) {
    			$all_blocks = $this->blocks[$id];
    			foreach ($all_blocks as $block) {
    				$new_block = clone($block);
    				$new_block->setId(null);
    				// if the block is not disabled.
    				if ($block->getEnabled()) {    	
    					// we set all widget of the block
    					if (isset($this->widgets[$id][$block->getId()]) && !empty($this->widgets[$id][$block->getId()])){
    						$all_widgets      = $this->widgets[$id][$block->getId()];
    						foreach ($all_widgets as $widget) {
    							if ($widget->getEnabled()) {
    								$new_widget = clone($widget);
    								$new_widget->setId(null);
    								$new_block->addWidget($new_widget);
    							}
    						}
    					}
    				}
    				$new_page->addBlock($new_block);
    			}
    		}
    		// we change the route name of the new page.
    		$randome = new \DateTime();
    		$new_page->setRouteName($page->getRouteName() . '_copy_' . $randome->getTimeStamp());
    		$new_page->setUrl($page->getUrl() . '/copy/' . $randome->getTimeStamp());
    		// we persist.
    		$em->persist($new_page);
    		$em->flush();
    		// we register the new page in the route cache manager.
    		$routeCacheManager = $this->getContainer()->get('bootstrap.route_cache');
    		$routeCacheManager->setGenerator();
    		$routeCacheManager->setMatcher();
    		// we set the new url in the locale.
    		$entity_translate_page = $this->translations[$id][$locale];
    		if (
    			($entity_translate_page instanceof \PiApp\AdminBundle\Entity\TranslationPage)
    			&&
    			($entity_translate_page->getSlug() != "")
    		) {
    			$new_url = $new_page->getUrl() . '/' . $entity_translate_page->getSlug();
    		} else {
    			$new_url = $new_page->getUrl();
    		}
    		$new_url = str_replace("//", "/", $new_url);
    		$new_url = preg_replace("/{[a-zA-Z0-9]+}/i", 'testValue', $new_url);
    		
    		return $new_url;
    	}
    	
    	return $this->getContainer()->get('bootstrap.RouteTranslator.factory')->getRoute('home_page', array('locale' => $locale));
    }
    
    /**
     * Refresh the cache of all elements of a page (TranslationPages, widgets, translationWidgets)
     *
     * @param  string    $type        ['page', 'block', 'widget']
     * @param  string    $entity
     * @return string                Returns the requested url.
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-04-03
     */
    public function getUrlByType($type, $entity = null)
    {
        $Url    = null;
        //
        switch ($type) {
            case 'page':
                if (is_int($entity)) {
                    $entity = $this->getPageById($entity);
                }
                if ($entity instanceof Page){
                    $Url['edit']         = $this->container->get('router')->generate('admin_pagebytrans_edit', array('id' => $entity->getId(), 'NoLayout' => true));
                }                
                $Url['new']         = $this->container->get('router')->generate('admin_pagebytrans_new', array('NoLayout' => true));            
                break;            
            case 'block':
                if (is_int($entity)) {
                    $entity = $this->getBlockById($entity);
                }
                if ($entity instanceof Block){                
                    $Url['admin']     = $this->container->get('router')->generate('admin_blockbywidget_show', array('id' => $entity->getId(), 'NoLayout' => true));
                    $Url['import']    = $this->container->get('router')->generate('public_importmanagement_widget', array('id_block' => $entity->getId(), 'NoLayout' => true));
                }                
                break;
            case 'widget':
                if (is_null($entity)) {
                    $entity = $this->getCurrentWidget();
                }
                if (is_int($entity)) {
                    $entity = $pageManager->getWidgetById($entity);
                }
                if ($entity instanceof Widget) {
                    $Url['move_up']   = $this->container->get('router')->generate('admin_widget_move_ajax', array('id' => $entity->getId(), 'type' => 'up'));
                    $Url['move_down'] = $this->container->get('router')->generate('admin_widget_move_ajax', array('id' => $entity->getId(), 'type' => 'down'));
                    $Url['delete']    = $this->container->get('router')->generate('admin_widget_delete_ajax', array('id' => $entity->getId()));
                    $Url['admin']     = $this->container->get('router')->generate('admin_widget_edit', array('id' => $entity->getId(), 'NoLayout' => true));
                    $Url['edit']      = $this->container->get('router')->generate('admin_homepage');
                    $Url['import']    = $this->container->get('router')->generate('public_importmanagement_widget', array('id_widget' => $entity->getId(), 'NoLayout' => true));
                    try {
                        $xmlConfig    = $entity->getConfigXml();
                        $xmlConfig    = new \Zend_Config_Xml($xmlConfig);
                        ////////////////// url management of gedmo snippet ///////////////////////////
                        if ( ($entity->getPlugin() == "gedmo") && $xmlConfig->widgets->get('gedmo') && $xmlConfig->widgets->gedmo->get('snippet') && $xmlConfig->widgets->gedmo->get('id') ){
                            $id_snippet = $xmlConfig->widgets->gedmo->get('id');
                            $is_snippet = $xmlConfig->widgets->gedmo->get('snippet');
                            if ($is_snippet && !empty($id_snippet)){
                                $entity = $this->getWidgetById($id_snippet);
                                $xmlConfig   = $entity->getConfigXml();
                                $xmlConfig   = new \Zend_Config_Xml($xmlConfig);                                
                            }
                        }
                        ////////////////// url management of all gedmo widget ///////////////////////////
                        if ( ($entity->getPlugin() == "gedmo") && $xmlConfig->widgets->get('gedmo') && $xmlConfig->widgets->gedmo->get('controller')) {
                            $infos        = explode(':', $xmlConfig->widgets->gedmo->controller);
                            $infos_entity = $infos[0] . ':' . str_replace('\\\\', '\\', $infos[1]);
                            $infos_method = strtolower($infos[2]);
                            $getAvailable = "getAvailable" . ucfirst(strtolower($entity->getAction()));                            
                            try {
                                $Lists    = \PiApp\AdminBundle\Util\PiWidget\PiGedmoManager::$getAvailable();
                            } catch (\Exception $e) {
                                $Lists = null;
                            }                            
//                             if ( $xmlConfig->widgets->gedmo->get('params') && $xmlConfig->widgets->gedmo->params->get('id') )
//                                 $params['id']        = $xmlConfig->widgets->gedmo->params->id;
//                             if ( $xmlConfig->widgets->gedmo->get('params') && $xmlConfig->widgets->gedmo->params->get('category') )
//                                 $params['category']    = $xmlConfig->widgets->gedmo->params->category;
                            $params['NoLayout']    = true;
                            if ( $xmlConfig->widgets->gedmo->get('params')) {
                                $params = array_merge($xmlConfig->widgets->gedmo->params->toArray(), $params);                            
                            }
                            //if (isset($Lists[$infos_entity][$infos_method]['edit']))
                            //    $Url['edit']         = $this->container->get('router')->generate($Lists[$infos_entity][$infos_method]['edit'], $params);
                            if (isset($Lists[$infos_entity][$infos_method]) && is_array($Lists[$infos_entity][$infos_method])) {
                                foreach($Lists[$infos_entity][$infos_method] as $action => $route_name){
                                    $Url[$action] = $this->container->get('router')->generate($route_name, $params);
                                }
                            }
                        }
                        ////////////////// url management of translation content widget ///////////////////////////
                        if ( ($entity->getPlugin() == "content") && $xmlConfig->widgets->get('content') ) {
                            if ( $xmlConfig->widgets->content->get('snippet') && $xmlConfig->widgets->content->get('id') ) {
                                $Url['edit'] = $this->container->get('router')->generate('admin_widget_edit', array('id' => $xmlConfig->widgets->content->get('id'), 'NoLayout' => true));
                            }
                        }
                        ////////////////// url management of all content widget ///////////////////////////
                        if ( ($entity->getPlugin() == "content") && $xmlConfig->widgets->get('content') && $xmlConfig->widgets->content->get('controller') ) {
                            $infos        = $xmlConfig->widgets->content->controller;
                            $getAvailable = "getAvailable" . ucfirst(strtolower($entity->getAction()));                            
                            if ($xmlConfig->widgets->content->get('params') && $xmlConfig->widgets->content->params->get('action')) {
                                $infos_method = $xmlConfig->widgets->content->params->action;
                            }
                            try {
                                $Lists = \PiApp\AdminBundle\Util\PiWidget\PiContentManager::$getAvailable();
                            } catch (\Exception $e) {
                                $Lists = null;
                            }                            
                            $params['NoLayout'] = true;
                            if ( $xmlConfig->widgets->content->get('params')) {
                                $params = array_merge($xmlConfig->widgets->content->params->toArray(), $params);                        
                            }
                            if (isset($Lists[$infos][$infos_method]) && is_array($Lists[$infos][$infos_method])) {
                                foreach($Lists[$infos][$infos_method] as $action => $route_name) {
                                    $Url[$action] = $this->container->get('router')->generate($route_name, $params);
                                }
                            }
                        }                        
                    } catch (\Exception $e) {
                    }
                }
                break;
        } // end switch
                
        return $Url;
    }    
    
    /**
     * Return all urls of a page
     * 
     * @param  \PiApp\AdminBundle\Entity\Page
     * @param  string    $type        ['sql']
     * @return array
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-06-21
     */
    public function getUrlByPage(\PiApp\AdminBundle\Entity\Page $page, $type = '')
    {        
        $urls = array();
        // we register all urls of the page
        foreach($page->getTranslations() as $key=>$translationPage) {
            if ($translationPage instanceof TranslationPage) {
                $locale = $translationPage->getLangCode()->getId();
                $url    = $page->getUrl();
                $slug   = $translationPage->getSlug();
                switch (true) {
                    case ( !empty($url) && !empty($slug) ):
                        $urls[$locale] = $url . "/" .$slug;
                        break;
                    case (!empty($url) && empty($slug)) :
                        $urls[$locale] = $url;
                        break;
                    case (empty($url) && !empty($slug)) :
                        $urls[$locale] = $slug;
                        break;
                    case (empty($url) && empty($slug)) :
                        $urls[$locale] = "";
                        break;
                }
                $is_prefix_locale = $this->container->getParameter("pi_app_admin.page.route.with_prefix_locale");
                if ($is_prefix_locale) {
                    $locale_tmp = explode('_', $locale);
                    $urls[$locale] = $locale_tmp[0] . '/' . $urls[$locale];
                }
                $urls[$locale]     = str_replace("//","/",$urls[$locale]);                
                if ($type == 'sql') {
                    $urls[$locale] = str_replace("/","\\\\\\\\\/",$urls[$locale]);
                }
            }
        }

        return $urls;
    }  
    
}