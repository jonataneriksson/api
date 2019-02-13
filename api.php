<?php

/**
 * Kirby API plugin.
 */

kirby()->routes(
array(
  array(
    'pattern' => 'api.json',
    'action'  => function() {

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Return object */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      $json = (object)[];

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !If structure is 0 then load the structure */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      $loadstructure = !get('structure');

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !If language is used. */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      $language = get('language');

      //Some metadata
      $before = microtime(true);

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Test String */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function fieldisstring($field)
      {
          try {
              if('object' == gettype($field)){
                return ('string' == gettype($field->value()));
              } else {
                return false;
              }
          } catch (Exception $exception) {
              return false;
          }
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !YAML */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function fieldisyaml($field)
      {
          try {
            $field->value = str_replace('*','_', $field->value);
            if('array' == gettype($field->yaml())){
              if('array' == gettype($field->yaml()[0])){
                //Let's search for spaces
                if (preg_match("/^[a-z]+$/", key($field->yaml()[0]))) {
                  return true;
                }
              }
            }
            return false;
          } catch (Exception $exception) {
            return false;
          }
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !YAML Test */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function canbeyaml($field)
      {
        try {
            return $field->yaml();
        } catch (Exception $exception) {
            return false;
        }
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get structure */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getstructure($input) {
        $return = [];
        foreach($input->toStructure() as $index => $structure):
          foreach($structure->toArray() as $key => $field):
            if(fieldisstring($field)){
              //Apparently *asteriks* can really mess the YAML parser
              if($field->value){
                $field->value = str_replace('*','_', $field->value);
              }
              $return[$index][$key] = getfield($field);
            } else if (fieldisyaml($field)) {
              $return[$index][$key] = getstructure($field);
            } else {
              //die(get_class($input->toStructure()));
              die(print_r($input));
              //die(print_r($input->toArray()));
            }
          endforeach;
        endforeach;
        return $return;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get field */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getfield($field) {
        $current_field = [];
        if (fieldisyaml($field)) {
          $current_field = getstructure($field);
        } else {
          $current_field['kirbytext'] = $field->kirbytext()->value();
          $current_field['value'] = $field->value();
        }
        return $current_field;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get fields */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getfields($page) {
        $contentitem = [];
        foreach($page->content(get('language'))->data() as $field):
          $contentitem[$field->key()] = getfield($field);
        endforeach;
        return $contentitem;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get site */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getsite()
      {
        $siteobject = site()->pages()->page()->site();
        $siteitem = (object) '';
        $siteitem->url = $siteobject->url();
        $siteitem->language = get('language');
        $siteitem->strings = (array)$siteobject->content($siteitem->language)->toArray();
        $sitecontentitem = [];
        foreach($siteobject->content($siteitem->language)->data() as $field):
          $sitecontentitem[$field->key()] = getfield($field);
        endforeach;
        $siteitem->content = $sitecontentitem;
        return $siteitem;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get page structure */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getpagestructures($pages) {

        //Let's make the return array
        $pageitems = (array)[];

        $index = 0;

        //Loop through pages
        foreach($pages as $page):

          //Save page data to array
          $pageitems[$page->uid()] = getpagestructure($page, $index);

          $index++;

        endforeach;

        //Return pages array
        return $pageitems;

      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get pages */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getpages($pages) {

        //Let's make the return array
        $pageitems = (array)[];

        $index = 0;

        //Loop through pages
        foreach($pages as $page):

          //Save page data to array
          $pageitems[$page->uid()] = getpage($page, $index);

          $index++;

        endforeach;

        //Return pages array
        return $pageitems;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Extend page */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function extendpage($page, $pageitem) {
        //If files get files
        $pageitem->files = ($page->hasFiles()) ? getfiles($page) : false;
        $pageitem->content = getfields($page);
        $pageitem->strings = (array)$page->content(get('language'))->toArray();
        $pageitem->template = (string)$page->intendedTemplate();
        $pageitem->folder = (string)$page->contentURL();
        $pageitem->extended = true;
        //Return page array
        return $pageitem;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get page */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getpagestructure($page, $index = 0) {

        //Let's make the return object
        $pageitem = (object) '';
        $pageitem->uri = (string)$page->uri();
        $pageitem->url = (string)$page->url();
        $pageitem->uid = (string)$page->uid();
        $pageitem->visible = (string)$page->isVisible();

        //Setup children
        if($page->hasChildren() && !get('structure')):
          $pageitem->children = getpagestructures($page->children());
        endif;

        //Extend page item
        if(get('path')==(string)$page->uri() || get('full')):
            $pageitem = extendpage($page, $pageitem);
        elseif('portfolio'==(string)$page->uri()):
                $pageitem = extendpage($page, $pageitem);
        else:
            $pageitem->extended = false;
        endif;

        //Return page array
        return $pageitem;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get page */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getpage($page, $index = 0) {

        //Let's make the return object
        $pageitem = (object) '';
        $pageitem->uri = (string)$page->uri();
        $pageitem->url = (string)$page->url();
        $pageitem->uid = (string)$page->uid();
        $pageitem->visible = (string)$page->isVisible();

        //Get strings only.
        if(get('language')) $pageitem->language = get('language');
        $pageitem->strings = (array)$page->content(get('language'))->toArray();

        //Setup children
        if($page->hasChildren() && !get('structure')):
          $pageitem->children = getpages($page->children());
        endif;

        //Extend page item
        if(get('path')==(string)$page->uri() || get('full')):
            $pageitem = extendpage($page, $pageitem);

            //Add some meta
            $pageitem->index = $index;
        else:
            $pageitem->extended = false;
        endif;

        //Return page array
        return $pageitem;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get files */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getfiles($page) {

        $index = 0;

        //Loop through files
        foreach($page->files()->sortBy('sort', 'asc') as $file):
          $fileitems[$file->filename()] = getfile($file);
          $fileitems[$file->filename()]['index'] = (string)$index;
          $index++;
        endforeach;

        //Return file array
        return $fileitems;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Get one file */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getfile($file) {

        $fileitem = [];
        $fileitem['name'] = (string)$file->name();
        $fileitem['type'] = (string)$file->type();
        $fileitem['extension'] = (string)$file->extension();
        $fileitem['src'] = (string)$file->url();

        if($fileitem['type'] == 'image'):
          $fileitem['height'] = (string)$file->height();
          $fileitem['width'] = (string)$file->width();
          $fileitem['ratio'] = (string)round($file->ratio()*100)/100;
          $fileitem['orientation'] = (string)$file->orientation();
          $fileitem['thumbnails'] = getthumbnails($file);
        endif;

        if($fileitem['type'] == 'video'):
          $stillfromvideo = $file->thumb(['clip' => true, 'still' => true]);
          $fileitem['height'] = (string)$stillfromvideo->height();
          $fileitem['width'] = (string)$stillfromvideo->width();
          $fileitem['ratio'] = (string)round($stillfromvideo->ratio()*100)/100;
          $fileitem['orientation'] = (string)$stillfromvideo->orientation();
          $fileitem['thumbnails'] = getthumbnails($file, ['clip' => true, 'still' => true]);
        endif;

        foreach($file->meta(get('language'))->data() as $key => $value):
          $fileitem['meta'][$key]['kirbytext'] = $file->meta(get('language'))->data()[$key]->kirbytext()->value();
          $fileitem['meta'][$key]['value'] = $file->meta(get('language'))->data()[$key]->value();
        endforeach;

        return $fileitem;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !Create thumbnails */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      function getthumbnails($file, $options = []) {
        $thumbnails = [];
        $heights = c::get('thumbs.heights') ? c::get('thumbs.heights') : ['360', '720', '1080'];
        foreach($heights as $height):
          $id = 'h' . $height;
          $options['height'] = $height;
          $thumbnails[$id] = (string)$file->thumb($options)->url();
        endforeach;
        return $thumbnails;
      }

      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
      /* !The echo */
      /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

      if(get('path')):
        if($page = site()->pages()->findByURI(get('path'))):
          $json->page = getpage($page);
        endif;
      endif;

      if($loadstructure):
        $json->site = getsite();
        $json->pages = getpages(site()->pages());
      endif;

      $after = microtime(true);
      $json->intime = $after - $before;
      return new Response(json_encode($json), 'json');

})));
