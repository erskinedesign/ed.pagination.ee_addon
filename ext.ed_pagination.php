<?php

/**
 * Erskine Design Pagination (PHP5 only)
 * REQUIRES ExpressionEngine 2+
 * 
 * @package     Ed_pagination_ext
 * @version     1.0.0
 * @author      Wil Linssen (Erskine Design)
 * @copyright   Copyright (c) 2011 Erskine Design
 * @license     http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 * 
 */

Class Ed_pagination_ext
{
    
    public $settings = array();
    public $name = 'ED pagination links';
    public $version = '0.1';
    public $description = 'Friendly pagination links';
    public $settings_exist = 'y';
    public $docs_url = 'http:/github.com/erskinedesign/';
    
    
    /**
    * Constructicons
    *
    * @return void
    */
    public function __construct( $settings = '' )
    {
        $this->EE =& get_instance();
    }
    
    
    /**
    * Activate Extension
    *
    * This function enters the extension into the exp_extensions table
    *
    * @see http://codeigniter.com/user_guide/database/index.html for
    * more information on the db class.
    *
    * @return void
    */
    function activate_extension()
    {
        
    	$data = array(
    		'class'		=> __CLASS__,
    		'method'	=> '_paginate',
    		'hook'		=> 'channel_module_create_pagination',
    		'settings'	=> serialize($this->settings),
    		'priority'	=> 1,
    		'version'	=> $this->version,
    		'enabled'	=> 'y'
    	);

    	$this->EE->db->insert('extensions', $data);
    }
    
    
    /**
    * Disable Extension
    *
    * This method removes information from the exp_extensions table
    *
    * @return void
    */
    function disable_extension()
    {
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');
    }
    
    
    /**
    * Paginate
    *
    * We're basically replicating EE's core functionality and adding our own stuff at the end
    *
    * @return void
    */    
    function _paginate(&$data)
    {
        
        $data->EE->extensions->end_script = TRUE;
        $query = $data->EE->db->query($data->pager_sql);
        $count  = ( !empty($query->num_rows) ) ? $query->num_rows : FALSE;
        
        // Only proceed if the option is set
        if ($data->paginate == TRUE)
        {

            $tagdata = $data->paginate_data;
            
            /* 
                What follows is pretty much a rip from the EE core, I'm subsidising it at the end
                but I should really just make better use of the hook?
            */

            /* --------------------------------------
            /*  For subdomain's or domains using $template_group and $template
            /*  in path.php, the pagination for the main index page requires
            /*  that the template group and template are specified.
            /* --------------------------------------*/

            if (($this->EE->uri->uri_string == '' OR $this->EE->uri->uri_string == '/') && $this->EE->config->item('template_group') != '' && $this->EE->config->item('template') != '')
            {
                $data->basepath = $this->EE->functions->create_url($this->EE->config->slash_item('template_group').'/'.$this->EE->config->item('template'));
            }

            if ($data->basepath == '')
            {
                $data->basepath = $this->EE->functions->create_url($this->EE->uri->uri_string);

                if (preg_match("#^P(\d+)|/P(\d+)#", $data->query_string, $match))
                {
                    $data->p_page = (isset($match[2])) ? $match[2] : $match[1];
                    $data->basepath = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $data->basepath));
                }
            }

            //  Standard pagination - base values

            if ($data->field_pagination == FALSE)
            {	
                if ($data->display_by == '')
                {
                    if ($count == 0)
                    {
                        $data->sql = '';
                        return;
                    }

                    $data->total_rows = $count;
                }

                if ($data->dynamic_sql == FALSE)
                {
                    $cat_limit = FALSE;
                    if ((in_array($data->reserved_cat_segment, explode("/", $this->EE->uri->uri_string))
                        AND $this->EE->TMPL->fetch_param('dynamic') != 'no'
                        AND $this->EE->TMPL->fetch_param('channel'))
                        OR (preg_match("#(^|\/)C(\d+)#", $this->EE->uri->uri_string, $match) AND $this->EE->TMPL->fetch_param('dynamic') != 'no'))
                    {
                        $cat_limit = TRUE;
                    }

                    if ($cat_limit AND is_numeric($this->EE->TMPL->fetch_param('cat_limit')))
                    {
                        $data->p_limit = $this->EE->TMPL->fetch_param('cat_limit');
                    }
                    else
                    {
                        $data->p_limit  = ( ! is_numeric($this->EE->TMPL->fetch_param('limit')))  ? $data->limit : $this->EE->TMPL->fetch_param('limit');
                    }
                }

                $data->p_page = ($data->p_page == '' OR ($data->p_limit > 1 AND $data->p_page == 1)) ? 0 : $data->p_page;

                if ($data->p_page > $data->total_rows)
                {
                    $data->p_page = 0;
                }

                $data->current_page = floor(($data->p_page / $data->p_limit) + 1);
                $data->total_pages = intval(floor($data->total_rows / $data->p_limit));				
            }
            else
            {
                //  Field pagination - base values

                if ($count == 0)
                {
                    $data->sql = '';
                    return;
                }

                $m_fields = array();

                foreach ($data->multi_fields as $val)
                {
                    foreach($data->cfields as $site_id => $cfields)
                    {
                        if (isset($cfields[$val]))
                        {
                            if (isset($row['field_id_'.$cfields[$val]]) AND $row['field_id_'.$cfields[$val]] != '')
                            {
                                $m_fields[] = $val;
                            }
                        }
                    }
                }

                $data->p_limit = 1;

                $data->total_rows = count($m_fields);

                $data->total_pages = $data->total_rows;

                if ($data->total_pages == 0)
                    $data->total_pages = 1;

                $data->p_page = ($data->p_page == '') ? 0 : $data->p_page;

                if ($data->p_page > $data->total_rows)
                {
                    $data->p_page = 0;
                }

                $data->current_page = floor(($data->p_page / $data->p_limit) + 1);

                if (isset($m_fields[$data->p_page]))
                {
                    $this->EE->TMPL->tagdata = preg_replace("/".LD."multi_field\=[\"'].+?[\"']".RD."/s", LD.$m_fields[$data->p_page].RD, $this->EE->TMPL->tagdata);
                    $this->EE->TMPL->var_single[$m_fields[$data->p_page]] = $m_fields[$data->p_page];
                }
            }

            //  Create the pagination

            if ($data->total_rows > 0 && $data->p_limit > 0)
            {
                if ($data->total_rows % $data->p_limit)
                {
                    $data->total_pages++;
                }				
            }

            if ($data->total_rows > $data->p_limit)
            {
                $this->EE->load->library('pagination');

                if (strpos($data->basepath, SELF) === FALSE && $this->EE->config->item('site_index') != '')
                {
                    $data->basepath .= SELF;
                }

                if ($this->EE->TMPL->fetch_param('paginate_base'))
                {
                    // Load the string helper
                    $this->EE->load->helper('string');

                    $data->basepath = $this->EE->functions->create_url(trim_slashes($this->EE->TMPL->fetch_param('paginate_base')));
                }

                $config['base_url']		= $data->basepath;
                $config['prefix']		= 'P';
                $config['total_rows'] 	= $data->total_rows;
                $config['per_page']		= $data->p_limit;
                $config['cur_page']		= $data->p_page;
                $config['first_link'] 	= $this->EE->lang->line('pag_first_link');
                $config['last_link'] 	= $this->EE->lang->line('pag_last_link');

                // Allows $config['cur_page'] to override
                $config['uri_segment'] = 0;

                $this->EE->pagination->initialize($config);
                $data->pagination_links = $this->EE->pagination->create_links();				


                if ((($data->total_pages * $data->p_limit) - $data->p_limit) > $data->p_page)
                {
                    $data->page_next = reduce_double_slashes($data->basepath.'/P'.($data->p_page + $data->p_limit));
                }

                if (($data->p_page - $data->p_limit ) >= 0)
                {
                    $data->page_previous = reduce_double_slashes($data->basepath.'/P'.($data->p_page - $data->p_limit));
                }
            }
            else
            {
                $data->p_page = '';
            }
            
            
            /* --------------------------------------
             * Now we use all that lovely data, and just look for our own tag
             * which we'll replace with nicer links, it'll ignore it if it's
             * not there.
            /* --------------------------------------*/
            $pattern = '/'.LD.'ed_pages'.RD.'(.*)'.LD.'\/ed_pages'.RD.'/si';
            if ( preg_match($pattern, $tagdata, $matches) )
            {
                // Build it!
                $links = '';
                for ( $i=1; $i<=$data->total_pages; $i++ )
                {
                    // The data between the tag pair
                    $link = $matches[1];
                    
                    // Just a string for now for current page
                    $cur = $i == $data->current_page ? 'cur' : '';
                    // href is either the basepath, or the current page * num on each page
                    $href = $i > 1 ? $data->basepath.'/P'.( ($i-1) * $data->p_limit) : $data->basepath;
                    
                    // Single variable replacements
                    $link = str_replace(LD.'href'.RD, $href, $link);
                    $link = str_replace(LD.'cur'.RD, $cur, $link);
                    $link = str_replace(LD.'page_no'.RD, $i, $link);
                    
                    // Add a new line for each link
                    $links .= $link."\n";
                }
                
                // Replace the original tag with our newly built one
                $data->paginate_data = str_replace($matches[0], $links, $data->paginate_data);
            }
            
        }

    }
    
}

/* End of file ext.ed_pagination.php */
/* Location: ./system/expressionengine/third_party/ed_pagination/ext.ed_pagination.php */