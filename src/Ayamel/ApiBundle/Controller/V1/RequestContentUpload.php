<?php

namespace Ayamel\ApiBundle\Controller\V1;

use Ayamel\ApiBundle\Controller\ApiController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class RequestContentUpload extends ApiController
{
    /**
     * Request a valid content upload url for a resource.  Note that a content upload url is only valid for one use only.
     * For more information on this, see the documentation for the upload route: `POST: /resources/{id}/content/{token}`
     *
     * For more specifics about uploading content, make sure to read through the documentation on the
     * [wiki](https://github.com/AmericanCouncils/AyamelResourceApiServer/wiki/Uploading-Content).
     *
     * @param string $id
     *
     * @ApiDoc(
     *      resource=true,
     *      description="Get a content upload url.",
     *      filters={
     *          {"name"="_format", "default"="json", "description"="Return format, can be one of xml, yml or json"},
     *      }
     * )
     */
    public function executeAction($id)
    {
        //get the resource
        $resource = $this->getRequestedResourceById($id);

        //check for deleted resource
        if ($resource->isDeleted()) {
            return $this->returnDeletedResource($resource);
        }

        $this->requireResourceOwner($resource);

        $uploadToken = $this->container->get('ayamel.api.upload_token_manager')->createTokenForId($resource->getId());

        $url = $this->container->get('router')->generate('api_v1_upload_content', array('id' => $resource->getId(), 'token' => $uploadToken), true);

        return $this->createServiceResponse(array('contentUploadUrl' => $url), 200);
    }

}
