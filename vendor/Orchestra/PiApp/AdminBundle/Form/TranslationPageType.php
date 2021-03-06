<?php
/**
 * This file is part of the <Admin> project.
 *
 * @category   Admin_Form
 * @package    CMS_Form
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-01-07
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PiApp\AdminBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use PiApp\AdminBundle\Repository\TranslationPageRepository;
use Doctrine\ORM\EntityRepository;

/**
 * Description of the TranslationPageType form.
 *
 * @category   Admin_Form
 * @package    CMS_Form
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class TranslationPageType extends AbstractType
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $_container;
    
    /**
     * @var string
     */
    protected $_locale;    
    
    /**
     * Constructor.
     *
     * @param \Doctrine\ORM\EntityManager $em
     * @return void
     */
    public function __construct($locale, ContainerInterface $container)
    {
        $this->_container     = $container;
        $this->_locale        = $locale;
    }    
        
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $format_date = $this->_container->get('pi_app_admin.twig.extension.tool')->getDatePatternByLocalFunction($this->_locale);
        
        $builder
            ->add('enabled', 'checkbox', array(
                    //'data'  => true,
                    'label'    => 'pi.form.label.field.enabled',
            ))
            ->add('secure', 'checkbox', array(
                    'label'    => 'pi.page.form.secure',
                    'required' => false,
            ))
            ->add('heritage', 'bootstrap_security_roles', array(
                    'multiple' => true,
                    'required' => false,
                    'label'    => 'pi.page.form.heritage',
                    "attr" => array(
                            "class"=>"pi_multiselect",
                    ),
            ))
            ->add('indexable', 'hidden', array(
                    'label'    => 'pi.page.form.indexable',
                    'required' => false,
            ))            
            ->add('published_at', 'date', array(
                    'widget' => 'single_text', // choice, text, single_text
                    'input' => 'datetime',
                    'format' => $format_date,// 'dd/MM/yyyy', 'MM/dd/yyyy',
                    "attr" => array(
                            "class"=>"pi_datepicker",
                    ),
                    'label'    => 'pi.form.label.date.publication',
            ))
            ->add('archive_at', 'date', array(
                    'widget' => 'single_text', // choice, text, single_text
                    'input' => 'datetime',
                    'format' => $format_date,// 'dd/MM/yyyy', 'MM/dd/yyyy',
                    "attr" => array(
                            "class"=>"pi_datepicker",
                    ),
                    'label'    => 'pi.form.label.date.archivage',
            ))
            ->add('langCode', 'entity', array(
                    'class' => 'PiAppAdminBundle:Langue',
                    'query_builder' => function(EntityRepository $er) {
                        return $er->createQueryBuilder('k')
                        ->select('k')
                        ->where('k.enabled = :enabled')
                        ->orderBy('k.label', 'ASC')
                        ->setParameter('enabled', 1);
                    },
                    'property' => 'label',
                    "label"    => "pi.form.label.field.language",
                    "attr" => array(
                            "class"=>"pi_simpleselect",
                    ),            
            ))
//             ->add('langStatus', 'choice', array(
//                     'choices'   => TranslationPageRepository::getAvailableLangStatus(),
//                     'required'  => true,
//                     'multiple'    => false,
//                     'expanded' => true,
//             ))            
            ->add('status', 'choice', array(
                    'choices'   => TranslationPageRepository::getAvailableStatus(),
                    'label'    => 'pi.page.form.status',
                    'required'  => true,
                    'multiple'    => false,
                    'expanded' => true,
            ))     
            ->add('tags', 'entity', array(
                    'class' => 'PiAppAdminBundle:Tag',
                    'query_builder' => function(EntityRepository $er) {
                        return $er->createQueryBuilder('k')
                        ->select('k')
                        ->where('k.enabled = :enabled')
                        ->orderBy('k.groupname', 'ASC')
                        ->setParameter('enabled', 1);
                    },
                    'multiple'    => true,
                    'required'  => false,
                    'label'    => 'pi.page.form.tags',
                    "attr" => array(
                            "class"=>"pi_multiselect",
                    ),
            ))
            //             ->add('tag', 'entity', array(
            //             		'class' => 'PiAppAdminBundle:Tag',
            //             		'query_builder' => function(EntityRepository $er) use ($id_tags) {
            //             			$translatableListener = $this->_container->get('gedmo.listener.translatable');
            //             			$translatableListener->setTranslationFallback(true);
            //             			return $er->createQueryBuilder('a')
            //             			->select('a')
            //             			->where("a.id IN (:id)")
            //             			->andWhere('a.enabled = 1')
            //             			->setParameter('id', $id_tags)
            //             			//->where("a.status = 'image'")
            //             			//->andWhere("a.image IS NOT NULL")
            //             			//->andWhere("a.enabled = 1")
            //             			->orderBy('a.id', 'ASC')
            //             			;
            //             		},
            //             		//'property' => 'title',
            //             		'empty_value' => 'pi.form.label.select.choose.tag',
            //             		'label' => "Tag",
            //             		'multiple' => true,
            //             		'required'  => false,
            //             		'constraints' => array(
            //             				new Constraints\NotBlank(),
            //             		),
            //             		"attr" => array(
            //             				"class"=>"pi_multiselect ajaxselect", // ajaxselect
            //             				"data-url"=>$this->_container->get('bootstrap.RouteTranslator.factory')->getRoute("admin_content_tag_selectentity_ajax"),
            //             				//"data-selectid" => json_encode($id_tags)
            //             				"data-max" => 40,
            //             		),
            //             		'widget_suffix' => '<a class="button-ui-mediatheque button-ui-dialog"
            //              				title="Ajouter un tag à la sélection"
            //              				data-title="Tags"
            //              				data-href="'.$this->_container->get('bootstrap.RouteTranslator.factory')->getRoute("admin_content_tag_new", array("NoLayout"=>"false", "category"=>'')).'"
            //              				data-selectid="#piapp_adminbundle_tagtype_id"
            //              				data-selecttitle="#piapp_adminbundle_tagtype_name"
            //              				data-insertid="#piapp_adminbundle_translationpagetype_tag"
            //              				data-inserttype="multiselect"
            //              				></a>',
            //             ))            
            ->add('breadcrumb', 'hidden', array(
                    'label'    => 'pi.page.form.breadcrumb',
                    'required' => false,
            ))
            ->add('slug', 'text', array(
                    'label'    => 'pi.page.form.slug',
                    'required' => false,
            ))
            ->add('meta_title', 'text', array(
                    "label" => "pi.form.label.field.meta_title",
                    'required' => false,
            ))
            ->add('meta_keywords', 'text', array(
                    "label" => "pi.form.label.field.meta_keywords",
                    'required' => false,
            ))
            ->add('meta_description', 'text', array(
                    "label" => "pi.form.label.field.meta_description",
                    'required' => false,
            ))
        ;
    }

    public function getName()
    {
        return 'piapp_adminbundle_translationpagetype';
    }
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'data_class' => 'PiApp\AdminBundle\Entity\TranslationPage',
        ));
    }    
}
