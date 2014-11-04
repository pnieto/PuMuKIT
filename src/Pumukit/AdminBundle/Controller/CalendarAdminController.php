<?php

namespace Pumukit\AdminBundle\Controller;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sylius\Bundle\ResourceBundle\Event\ResourceEvent;

class CalendarAdminController extends AdminController
{

  /**
   * @var array
   */
  static $daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
  
  /**
   * Overwrite to get the calendar
   */
  public function indexAction(Request $request)
  {
    $config = $this->getConfiguration();

    $criteria = $config->getCriteria();
    $sorting = $config->getSorting(); 

    if (array_key_exists('reset', $criteria)) {
      $this->get('session')->remove('admin/'.$config->getResourceName().'/criteria');
    } elseif ($criteria){
      $this->get('session')->set('admin/'.$config->getResourceName().'/criteria', $criteria);
    }
    $criteria = $this->get('session')->get('admin/'.$config->getResourceName().'/criteria', array());

    //TODO: do upstream
    $new_criteria = array();
    foreach ($criteria as $property => $value) {
      //preg_match('/^\/.*?\/[imxlsu]*$/i', $e)
      if ('' !== $value) {
	$new_criteria[$property] = new \MongoRegex('/' . $value . '/i');
      }
    }
    $criteria = $new_criteria;

    $pluralName = $config->getPluralResourceName();
    $repository = $this->getRepository();

    if ($config->isPaginated()) {
      $resources = $this
	->getResourceResolver()
	->getResource($repository, $config, 'createPaginator', array($criteria, $sorting))
	;

      if ($request->get('page', null)) {
	$this->get('session')->set('admin/'.$config->getResourceName().'/page', $request->get('page', 1));
      }

      $resources
	->setCurrentPage($this->get('session')->get('admin/'.$config->getResourceName().'/page', 1), true, true)
	->setMaxPerPage($config->getPaginationMaxPerPage())
	;
    } else {
      $resources = $this
	->getResourceResolver()
	->getResource($repository, $config, 'findBy', array($criteria, $sorting, $config->getLimit()))
	;
    }


    if ("cal" == $request->query->get('cal')){
      $this->get('session')->set('admin/'.$config->getResourceName().'/cal', 'cal');
    }else if ("list" == $request->query->get('cal')){
      $this->get('session')->remove('admin/'.$config->getResourceName().'/cal');
    }

    if ($this->get('session')->has('admin/'.$config->getResourceName().'/cal')){
      list($m, $y, $calendar) = $this->getCalendar($config, $request);
      $resources = array($pluralName => $resources, 'm' => $m, 'y' => $y, 'calendar' => $calendar);
    }else{
      $resources = array($pluralName => $resources);
    }


    $view = $this
      ->view()
      ->setTemplate($config->getTemplate('index.html'))
      ->setData($resources)
      ;

    return $this->handleView($view);
  }


    /**
     * Overwrite to update the session.
     */
    public function delete($resource)
    {
        $config = $this->getConfiguration();
        $event = $this->dispatchEvent('pre_delete', $resource);

        if (!$event->isStopped()) {
            $this->get('session')->remove('admin/'.$config->getResourceName().'/id');
            $this->removeAndFlush($resource);
        }


        return $event;
    }



    public function batchDeleteAction(Request $request)
    {
        $ids = $this->getRequest()->get('ids');

	foreach($ids as $id) {
            $resource = $this->find($id);
	    $this->delete($resource);
	}
	$config = $this->getConfiguration();

	$this->setFlash('success', 'delete');

	return $this->redirectToRoute(
	    $config->getRedirectRoute('index'),
	    $config->getRedirectParameters()
	);
    }


  /**
   *
   */
  private function getCalendar($config, $request)
  {

    /*if (!$this->getUser()->hasAttribute('page', 'tv_admin/event'))
      $this->getUser()->setAttribute('page', 1, 'tv_admin/event');*/
    
    /*if($this->hasRequestParameter("cal")){
      $this->div_url = '?cal=cal';
      $this->div = "calendar";
    }else{
      $this->div_url = '';
      $this->div = "array";
      }*/

    if (!$this->get('session')->get('admin/'.$config->getResourceName().'/month'))
      $this->get('session')->set('admin/'.$config->getResourceName().'/month', date('m'));
    if (!$this->get('session')->get('admin/'.$config->getResourceName().'/year'))
      $this->get('session')->set('admin/'.$config->getResourceName().'/year', date('Y'));
    
    $m = $this->get('session')->get('admin/'.$config->getResourceName().'/month');
    $y = $this->get('session')->get('admin/'.$config->getResourceName().'/year');

    //$this->total_event_all = EventPeer::doCount(new Criteria());

    if ($request->query->get('month') == "next"){
      $changed_date = mktime(0,0,0,$m+1,1,$y);
      $this->get('session')->set('admin/'.$config->getResourceName().'/year', date("Y", $changed_date));
      $this->get('session')->set('admin/'.$config->getResourceName().'/month', date("m", $changed_date));
    }elseif ($request->query->get('month') == "previous"){
      $changed_date = mktime(0,0,0,$m-1,1,$y);
      $this->get('session')->set('admin/'.$config->getResourceName().'/year', date("Y", $changed_date));
      $this->get('session')->set('admin/'.$config->getResourceName().'/month', date("m", $changed_date));
    }elseif ($request->query->get('month') == "today"){
      $this->get('session')->set('admin/'.$config->getResourceName().'/year', date("Y"));
      $this->get('session')->set('admin/'.$config->getResourceName().'/month', date("m"));
    }

    $m = $this->get('session')->get('admin/'.$config->getResourceName().'/month', date('m'));
    $y = $this->get('session')->get('admin/'.$config->getResourceName().'/year', date('Y'));

    $calendar = $this->generateArray($m, $y);

    return (array($m, $y, $calendar));
  }

  /**
   * Get days in month
   */
  private static function getDaysInMonth($month, $year)
  {
    if ($month < 1 || $month > 12){
      return 0;
    }

    $d = self::$daysInMonth[$month - 1];

    if ($month == 2){
      if ($year%4 == 0){
        if ($year%100 == 0){
          if ($year%400 == 0){
            $d = 29;
          }
        }else{
          $d = 29;
        }
      }
    }
    return $d;
  }

  /**
   * Generate array
   */
  private static function generateArray($month, $year){
    $aux = array();

    $dweek = date('N', mktime(0,0,0,$month, 1, $year)) - 1;
    foreach(range(1, self::getDaysInMonth($month, $year)) as $i){
      $aux[intval($dweek / 7)][($dweek % 7)] = $i;
      $dweek++;
    }
    return $aux;
  }




}