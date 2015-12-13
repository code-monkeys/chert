<?php
namespace pygillier\Chert\Provider;

use pygillier\Chert\Exception;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class ControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        // creates a new controller based on the default route
        $controllers = $app['controllers_factory'];

        $controllers
            ->get('/', "\\pygillier\\Chert\\Provider\\ControllerProvider::indexAction")
            ->bind('home');
        $controllers
            ->post('/min', "\\pygillier\\Chert\\Provider\\ControllerProvider::minifyAction")
            ->bind('send_url');
        $controllers
            ->get('/status/{key}', "\\pygillier\\Chert\\Provider\\ControllerProvider::statusAction")
            ->bind('status');
		$controllers
            ->get('/done/{hash}', "\\pygillier\\Chert\\Provider\\ControllerProvider::doneAction")
            ->bind('done');
        $controllers
            ->get('/{hash}', "\\pygillier\\Chert\\Provider\\ControllerProvider::showAction")
            ->bind('show');

		return $controllers;
    }
    
    /** 
     * Homepage
     */
    public function indexAction(Application $app) 
    {
        return $app['twig']->render('index.twig');
    }
    
    public function minifyAction(Application $app, Request $request)
    {
	    $url = $request->get('url');

    	// Validate given URL
    	if( count($app['validator']->validateValue($url, new Assert\Url())) == 0)
	    {

		    $hash = $app['chert']->minify($url);
			return $app->redirect($app["url_generator"]->generate("done", array('hash' => $hash)));
	    }
	    else 
		    throw new \Exception("Invalid URL provided: ${url}");
    }
    
    public function statusAction(Application $app, Request $request, $key)
    {
        // Status is available in debug mode only.
        if(!$app['config']['show_status'] || $key != $app['config']['status_key'])
            throw new \Exception("Unauthorized access");

        $offset = $request->get('page', 0);
        $limit = $app['config']['status_links_per_page'];
        // DB access
        try
        {
			$links = $app['chert']->getListing($offset, $limit);
			$links = array_map(function($item) use ($app) {
				$item['hash'] = $app['hash_service']->getHash($item['id']);
				return $item;
			}, $links);

			return $app['twig']->render('status.twig', array(
				'count' => $app['chert']->countLinks(),
				'links' => $links,
			));
        }
        catch(\PDOException $err)
        {
            throw new Exception("An error occured during processing.".$err->getMessage());
        }

    }
	
	public function doneAction(Application $app, $hash)
	{
		$link = $app['chert']->expand($hash);
		
		return $app['twig']->render('done.twig', array(
			'link' => $link,
			'hash' => $hash,
		));
	}
    
    public function showAction(Application $app, $hash)
    {
		// Get the original link
		$link = $app['chert']->expand($hash);

		if($link['url'])
		{
			if(true === $app['config']['auto_redirect'])
				return $app->redirect($link['url']);
			else
			{
				return $app['twig']->render('show.twig', array(
					'link' => $link['url'],
				));
			}
		}
		else
			throw new \Exception("Unknown id provided");
	}
}
