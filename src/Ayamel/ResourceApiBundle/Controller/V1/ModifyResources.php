<?php
namespace Ayamel\ResourceApiBundle\Controller\V1;

use Ayamel\ResourceApiBundle\Controller\ApiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ayamel\ResourceBundle\Document\Resource;
use Ayamel\ResourceBundle\Document\Relation;

/**
 * Accepts data to edit multiple objects.
 *
 * @author Evan Villemez
 */
class ModifyResources extends ApiController {
        
    public function executeAction(Request $request) {
        throw $this->createHttpException(501);
    }

}
