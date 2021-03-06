<?php
/**
 * This file is part of the <Admin> project.
 * 
 * @category   Admin_Repositories
 * @package    Repository
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2011-12-28
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PiApp\AdminBundle\Repository;

use Doctrine\ORM\EntityRepository;
use BootStrap\TranslationBundle\Repository\TranslationRepository;

/**
 * Page Repository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 * 
 * @category   Admin_Repositories
 * @package    Repository
 * 
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class PageRepository extends TranslationRepository
{
    // Content types
    const TYPE_TEXT_HTML        = 'text/html';
    const TYPE_TEXT_CSS            = 'text/css';
    const TYPE_TEXT_JAVASCRIPT    = 'text/javasript';
        
    
    /**
     * Return list of available content types for all type pages.
     *
     * @return array
     * @access public
     * @static
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2011-12-28
     */
    public static function getAvailableContentTypes()
    {
        return array(
                self::TYPE_TEXT_HTML        => self::TYPE_TEXT_HTML,
                self::TYPE_TEXT_CSS           => self::TYPE_TEXT_CSS,
                self::TYPE_TEXT_JAVASCRIPT    => self::TYPE_TEXT_JAVASCRIPT
        );
    }
    
    /**
     * Return list of available content types for css/js type pages.
     *
     * @return array
     * @access public
     * @static
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2011-12-28
     */
    public static function getAvailableCssJsContentTypes()
    {
        return array(
                self::TYPE_TEXT_CSS           => self::TYPE_TEXT_CSS,
                self::TYPE_TEXT_JAVASCRIPT    => self::TYPE_TEXT_JAVASCRIPT
        );
    }    
    
    /**
     * Return home page
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getHomePage()
    {
        // Retrieve home page (page without parent_id)
        $page = $this->findOneBy(array('route_name' => 'home_page'));
    
        return $page;
    }

    /**
     * Return a page by url and slug
     *
     * @param string    $url    url value of a page
     * @param string    $slug    slug value of a translation of a page
     * 
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getPageByUrlAndSlug($url, $slug)
    {
        $query = $this->createQueryBuilder('p')
        ->select('p, t')
        ->leftJoin('p.translations', 't')
        ->leftJoin('p.blocks', 'b')
        ->where('p.enabled = :enabled')
        ->andwhere('p.meta_content_type = :meta')
        ->andWhere('p.url = :urlID')
        ->andWhere('t.slug = :slugID')        
        ->setParameters(array(
                'enabled'    => 1,
                'urlID'        => $url,
                'slugID'    => $slug,
                'meta'        => self::TYPE_TEXT_HTML
        ));
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
        
        // This ensures that there is a line in return.
        return $query->getQuery()->getOneOrNullResult();
    }
    
    /**
     * Return all html type pages.
     *
     * @param int    $user_id    user id
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getAllPageHtml($user_id = null)
    {
        $query = $this->createQueryBuilder('p')
        ->select('p, u')
        ->leftJoin('p.user', 'u')
        ->where('p.meta_content_type = :meta')
        ->setParameters(array(
                'meta'        => self::TYPE_TEXT_HTML
        ));
        
        if (!is_null($user_id))
            $query->Andwhere('u.id = :userID')
            ->setParameters(array(
                    'meta'        => self::TYPE_TEXT_HTML,
                    'userID'    => $user_id
            ));
        else
            $query->setParameters(array(
                    'meta'        => self::TYPE_TEXT_HTML,
            ));
            
        return $query;
    }    
    
    /**
     * Return all Css/js type pages.
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getAllPageCssJs()
    {
        $query = $this->createQueryBuilder('p')
        ->select('p')
        ->where('p.meta_content_type = :meta1')
        ->orwhere('p.meta_content_type = :meta2')
        ->setParameters(array(
                'meta1'        => self::TYPE_TEXT_CSS,
                'meta2'        => self::TYPE_TEXT_JAVASCRIPT,
        ));
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
    
        return $query;
    }
        
    /**
     * Return all Css type pages.
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getAllPageCss()
    {
        $query = $this->createQueryBuilder('p')
        ->select('p')
        ->where('p.meta_content_type = :meta')
        ->setParameters(array(
                'meta'        => self::TYPE_TEXT_CSS,
        ));
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
    
        return $query;
    }

    /**
     * Return all Js type pages.
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getAllPageJs()
    {
        $query = $this->createQueryBuilder('p')
        ->select('p')
        ->where('p.meta_content_type = :meta')
        ->setParameters(array(
                'meta'        => self::TYPE_TEXT_JAVASCRIPT,
        ));
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
    
        return $query;
    }
    
    /**
     * Return all pages of a rubrique by id.
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getAllPageByRubriqueId($idRubrique)
    {
        $query = $this->createQueryBuilder('p')
        ->select('p, r')
        ->leftJoin('p.rubrique', 'r')
        ->where('r.id = :rubriqueID')
        ->setParameters(array(
            'rubriqueID'        => $idRubrique,
        ));        
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
    
        return $query;
    }

    /**
     * Return all pages of a status
     * 
     * @param string $lang        locale language value
     * @param string $status    status of the translation page
     * @param int     $user_id    user id
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getAllPageByStatus($lang, $status, $user_id = null)
    {
        $query = $this->createQueryBuilder('p')
        ->select('p, t, u')
        ->leftJoin('p.translations', 't')
        ->leftJoin('p.user', 'u')
        ->where('p.meta_content_type = :meta')
        ->andWhere('t.status = :status')
        ->andWhere('t.langCode = :langCode');
        
        if (!is_null($user_id) && !is_null($lang))
            $query->Andwhere('u.id = :userID')
            ->setParameters(array(
                    'meta'        => self::TYPE_TEXT_HTML,
                    'status'    => $status,
                    'langCode'    => $lang,
                    'userID'    => $user_id,
            ));
        else
            $query->setParameters(array(
                    'meta'        => self::TYPE_TEXT_HTML,
                    'status'    => $status,
                    'langCode'    => $lang,
            ));
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
    
        return $query;
    }

    
    /**
     * Return all pages of a rubrique by id.
     *
     * @return \PiApp\AdminBundle\Entity\Page
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-01-23
     */
    public function getPageByLocale($id, $locale)
    {
        $query = $this->createQueryBuilder('p')
        ->select('p, t')
        ->innerJoin('p.translations', 't')
        ->where('p.id = :pageID')
        ->andWhere('t.langCode = :localeID')
        ->setParameters(array(
                'pageID'    => $id,
                'localeID'    => $locale,
        ));
        //return $query->getQuery()->setMaxResults(1)->getArrayResult();
    
        return $query;
    }    
    
}