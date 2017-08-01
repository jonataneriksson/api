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

       //Some metadata
       $before = microtime(true);
       $language = get('language') ? get('language') : 'en';

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

       //Yaml doesn't like '*' characters.
       //$field->value = str_replace('*','_', $field->value);


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
                 //die(print_r($field->toStructure()->toArray()[4]->toArray()['text']->kirbytext()));
                 //die(print_r(get_class($field->toStructure()->toArray()[0])));
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

       function test($A, $B)
       {
         die(print_r(key($A->yaml()[0])));
         //die(print_r($field->kirbytext()));
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
         //If field is not YAML it's plain content else it's structured content
         if (fieldisyaml($field)) {
           $current_field = getstructure($field);
         } else {
           $current_field['kirbytext'] = $field->kirbytext()->value();
           $current_field['value'] = $field->value();
           //$current_field['yaml'] = $field->yaml();
         }
         return $current_field;
       }

       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
       /* !Get fields */
       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

       function getfields($page) {
         global $language;
         $contentitem = [];
         foreach($page->content($language)->data() as $field):
           $contentitem[$field->key()] = getfield($field);
         endforeach;
         return $contentitem;
       }

       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
       /* !Get site */
       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

       function getsite()
       {
         global $language;
         $siteobject = site()->pages()->page()->site();
         $siteitem = (object) '';
         $siteitem->url = $siteobject->url();
         $siteitem->strings = (array)$siteobject->content($language)->toArray();
         $sitecontentitem = [];
         foreach($siteobject->content($language)->data() as $field):
           $sitecontentitem[$field->key()] = getfield($field);
         endforeach;
         $siteitem->content = $sitecontentitem;
         return $siteitem;
       }

       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
       /* !Get pages */
       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

       function getpages($pages) {

         //Let's make the return array
         //$pageitems = (array)[];

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
         //Get YAML fields
         //$pageitem->content = getfields($page);
         //If files get files
         $pageitem->files = ($page->hasFiles()) ? getfiles($page) : false;
         //Return page array
         return $pageitem;
       }

       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
       /* !Get page */
       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

       function getpage($page, $index = 0) {

         //Let's make the return object
         $pageitem = (object) '';
         global $language;

         //Get structured fields
         if(get('path')== $page ? (string)$page->uri() : '/'):
           $pageitem->content = getfields($page);
         endif;

         //Get strings only.
         $pageitem->strings = (array)$page->content($language)->toArray();
         $pageitem->language = (string)$page->content()->language();
         $pageitem->uri = (string)$page->uri();
         $pageitem->index = $index;
         $pageitem->template = (string)$page->intendedTemplate();
         $pageitem->url = (string)$page->url();
         $pageitem->folder = (string)$page->contentURL();
         $pageitem->visible = (string)$page->isVisible();
         $pageitem->uid = (string)$page->uid();
         $pageitem->parenttitle = (string)$page->parent()->title();
         $pageitem->parenturl = (string)$page->parent()->url();
         $pageitem->parentuid = (string)$page->parent()->uid();

         if($next = $page->next()):
           $pageitem->nexturi = (string)$next->uri();
           $pageitem->nexttitle = (string)$next->title();
           $pageitem->nexturl = (string)$next->url();
         endif;

         if($prev = $page->prev()):
           $pageitem->prevuri = (string)$prev->uri();
           $pageitem->prevtitle = (string)$prev->title();
           $pageitem->prevurl = (string)$prev->url();
         endif;

         //Setup children
         if($page->hasChildren() && !get('structure')):
           $pageitem->children = getpages($page->children());
         endif;

         //Extend page item
         if(get('path')==(string)$page->uri()):
           $pageitem = extendpage($page, $pageitem);
           $pageitem->extended = true;
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
         global $language;

         //Loop through files
         foreach($page->files()->sortBy('sort', 'asc') as $file):

           $fileitems[$file->filename()]['index'] = (string)$index;
           $fileitems[$file->filename()]['name'] = (string)$file->name();
           $fileitems[$file->filename()]['type'] = (string)$file->type();
           $fileitems[$file->filename()]['files'][$file->extension()] = (string)$file->url();
           $fileitems[$file->filename()][$file->type()] = (string)$file->url();
           $fileitems[$file->filename()]['orientation'] = (string)$file->orientation();
           $fileitems[$file->filename()]['height'] = (string)$file->height();
           $fileitems[$file->filename()]['width'] = (string)$file->width();
           $fileitems[$file->filename()]['src'] = (string)$file->url();
           if($file->type() == 'image'):
             $fileitems[$file->filename()]['thumbnails']['h350'] = (string)$file->thumb(['height' => 350])->url();
             $fileitems[$file->filename()]['thumbnails']['h700'] = (string)$file->thumb(['height' => 700])->url();
             $fileitems[$file->filename()]['thumbnails']['h1000'] = (string)$file->thumb(['height' => 1000])->url();
             $fileitems[$file->filename()]['ratio'] = (string)round($file->ratio()*100)/100;
           endif;
           if($file->type() == 'video'):
             $fileitems[$file->filename()]['thumbnails']['still']['h350'] = (string)$file->thumb(['height' => 350, 'clip' => true, 'still' => true])->url();
             $fileitems[$file->filename()]['thumbnails']['still']['h700'] = (string)$file->thumb(['height' => 700, 'clip' => true, 'still' => true])->url();
           endif;

           foreach($file->meta($language)->data() as $key => $value):
             $fileitems[$file->filename()]['meta'][$key]['kirbytext'] = $file->meta($language)->data()[$key]->kirbytext()->value();
             $fileitems[$file->filename()]['meta'][$key]['value'] = $file->meta($language)->data()[$key]->value();
           endforeach;
           $index++;

         endforeach;

         //Return file array
         return $fileitems;
       }

       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
       /* !The echo */
       /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

       if(!get('structure')):
         $json->site = getsite();
         $json->pages = getpages(site()->pages());
       elseif(get('path')):
         $json->page = getpage(site()->pages()->findByURI(get('path')));
       else:
         //TODO: Cover is not an universal frontpage
         $json->page = getpage(site()->pages()->find('cover'));
       endif;

       $after = microtime(true);
       $json->intime = $after - $before;
       return new Response(json_encode($json), 'json');

     }
     )
     )
   );
