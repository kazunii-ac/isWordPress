<?php

class isWordPress {

// puropati-
    public $url;
    public $result;
    public $msgResultEn;
    public $msgResultJa;
    public $parseUrl;
    public $isExist;
    public $directories;
    public $urlsInHtml;
    public $urlsRatio;
    public $score;

// mesoddo
    public function isWordPress($urlForCheck) {
        $strForPregMatchUrls = '(https?://[-_.!~*\'()a-zA-Z0-9;/?:@&=+$,%#]+)';

        $this->url = $urlForCheck;

        $tempArr = parse_url($urlForCheck);
        $this->parseUrl = new parseUrl();
        if (isset($tempArr['host'])) {
            $this->parseUrl->domain = $tempArr['host'];
        } else {
            $this->parseUrl->domain = 'no domain';
        }
        if (isset($tempArr['scheme'])) {
            $this->parseUrl->scheme = $tempArr['scheme'];
        } else {
            $this->parseUrl->scheme = 'no scheme';
        }

        $myDomain = $_SERVER["HTTP_HOST"];
        if (strpos($this->url, $myDomain) !== false) {
            //its myself!!!
            $this->result = false;
        } else {
            //check html source
            $htmlSrc = @file_get_contents($this->url);

            //wordpress unique character string
            $this->isExist = new isExistInHtml();
            if (strpos($htmlSrc, 'WordPress') === false) {
                $this->isExist->WordPress = false;
            } else {
                $this->isExist->WordPress = true;
            }
            if (strpos($htmlSrc, 'by WordPress') === false) {
                $this->isExist->byWordPress = false;
            } else {
                $this->isExist->byWordPress = true;
            }
            if (strpos($htmlSrc, 'Powered by WordPress') === false) {
                $this->isExist->PoweredbyWordPress = false;
            } else {
                $this->isExist->PoweredbyWordPress = true;
            }

            //get urls in html src
            preg_match_all($strForPregMatchUrls, $htmlSrc, $urlsInHtml);
            $tempArr = array();
            foreach ($urlsInHtml[0] as $oneUrl) {
                $tempUrlInHtml = new urlInHtml();
                $tempParse = parse_url($oneUrl);
                $tempUrlInHtml->url = $oneUrl;
                if ($tempParse === false) {
                    $tempUrlInHtml->scheme = 'not exist';
                    $tempUrlInHtml->isOwnDomain = true;
                } else {
                    $tempUrlInHtml->scheme = (isset($tempParse['scheme']) ? $tempParse['scheme'] : 'not exist');
                    if (isset($tempParse['host'])) {
                        $tempUrlInHtml->isOwnDomain = (strpos($this->parseUrl->domain, $tempParse['host']) === false ? false : true);
                    } else {
                        $tempUrlInHtml->isOwnDomain = true;
                    }
                }
                $tempUrlInHtml->isExist_wp = (strpos($oneUrl, 'wp') === false ? false : true);
                $tempUrlInHtml->isExist_wpcontent = (strpos($oneUrl, 'wp-content') === false ? false : true);
                $tempUrlInHtml->isExist_wpincludes = (strpos($oneUrl, 'wp-includes') === false ? false : true);

                $tempArr[] = $tempUrlInHtml;
            }
            $this->urlsInHtml = $tempArr;

            //check directories
            $tempDirectoriesArr = $this->retDirectories($urlForCheck);
            $directriesArr;
            foreach ($tempDirectoriesArr as $oneDirectory) {
                $tempOneDirectoryCls = new directoryCheck();
                $tempOneDirectoryCls->url = $oneDirectory;

                //sitemap.xml exist?
                $xmlSrc = @file_get_contents($oneDirectory . 'sitemap.xml');
                $urlsInSitemapXml;
                if ($xmlSrc === false) {
                    //sitemap.xml none
                    $tempOneDirectoryCls->isExist_sitemap_xml = false;
                } else {
                    //sitemap.xml exist
                    $tempOneDirectoryCls->isExist_sitemap_xml = true;
                    preg_match_all($strForPregMatchUrls, $xmlSrc, $urlsInSitemapXml);
                    foreach ($urlsInSitemapXml[0] as $oneUrlInSitemapXml) {
                        if (strpos($oneUrlInSitemapXml, '.xsl') !== false) {
                            //.xsl exist
                            $xslSrc = @file_get_contents($oneUrlInSitemapXml);
                            preg_match_all($strForPregMatchUrls, $xmlSrc, $urlsInSitemapXsl);
                            $tempOneDirectoryCls->isExist_wordpress_org_in_sitemap = false;
                            //if exist, true
                            foreach ($urlsInSitemapXsl[0] as $oneUrlInSitemapXsl) {
                                if (strpos($oneUrlInSitemapXsl, 'wordpress.org') !== false) {
                                    $tempOneDirectoryCls->isExist_wordpress_org_in_sitemap = true;
                                }
                            }
                        }
                    }
                }

                //check login page
                $wpLoginSrc = @file_get_contents($oneDirectory . 'wp-login.php');
                if (strpos($wpLoginSrc, 'Powered by WordPress') === false) {
                    $tempOneDirectoryCls->isExist_wp_login = false;
                } else {
                    $tempOneDirectoryCls->isExist_wp_login = true;
                }

                //check readme.html
                $readmeHtmlSrc = @file_get_contents($oneDirectory . 'readme.html');
                $tempOneDirectoryCls->isExist_wordpress_org_in_readme_html = $this->checkReadmeHtml($readmeHtmlSrc, $strForPregMatchUrls);
                if ($readmeHtmlSrc === false) {
                    $tempOneDirectoryCls->isExist_readme_html = false;
                    $tempOneDirectoryCls->isExist_wordpress_org_in_readme_html = false;
                } else {
                    $tempOneDirectoryCls->isExist_readme_html = true;
                    $tempOneDirectoryCls->isExist_wordpress_org_in_readme_html = $this->checkReadmeHtml($readmeHtmlSrc, $strForPregMatchUrls);
                }
                $directriesArr[] = $tempOneDirectoryCls;
            }
            $this->directories = $directriesArr;

            //url ratio calc
            $this->urlsRatio = new urlRatio();
            $this->urlsRatio->allUrlsCnt = 0;
            $this->urlsRatio->allWpContentCnt = 0;
            $this->urlsRatio->allWpIncludesCnt = 0;
            $this->urlsRatio->ownDomainUrlsCnt = 0;
            $this->urlsRatio->ownDomainWpContentCnt = 0;
            $this->urlsRatio->ownDomainWpIncludesCnt = 0;
            foreach ($this->urlsInHtml as $oneUrl) {
                if ($oneUrl->isOwnDomain === true) {
                    $this->urlsRatio->allUrlsCnt++;
                    $this->urlsRatio->ownDomainUrlsCnt++;
                    if ($oneUrl->isExist_wpcontent === true) {
                        $this->urlsRatio->allWpContentCnt++;
                        $this->urlsRatio->ownDomainWpContentCnt++;
                    }
                    if ($oneUrl->isExist_wpincludes === true) {
                        $this->urlsRatio->allWpIncludesCnt++;
                        $this->urlsRatio->ownDomainWpIncludesCnt++;
                    }
                } else {
                    $this->urlsRatio->allUrlsCnt++;
                    if ($oneUrl->isExist_wpcontent === true) {
                        $this->urlsRatio->allWpContentCnt++;
                    }
                    if ($oneUrl->isExist_wpincludes === true) {
                        $this->urlsRatio->allWpIncludesCnt++;
                    }
                }
            }
            if ($this->urlsRatio->allUrlsCnt === 0) {
                $this->urlsRatio->allWpContentRatio = 0;
            } else {
                $this->urlsRatio->allWpContentRatio = $this->urlsRatio->allWpContentCnt / $this->urlsRatio->allUrlsCnt;
            }
            if ($this->urlsRatio->allUrlsCnt === 0) {
                $this->urlsRatio->allWpIncludesRatio = 0;
            } else {
                $this->urlsRatio->allWpIncludesRatio = $this->urlsRatio->allWpIncludesCnt / $this->urlsRatio->allUrlsCnt;
            }
            if ($this->urlsRatio->ownDomainUrlsCnt === 0) {
                $this->urlsRatio->ownDomainWpContentRatio = 0;
            } else {
                $this->urlsRatio->ownDomainWpContentRatio = $this->urlsRatio->ownDomainWpContentCnt / $this->urlsRatio->ownDomainUrlsCnt;
            }
            if ($this->urlsRatio->ownDomainUrlsCnt === 0) {
                $this->urlsRatio->ownDomainWpIncludesRatio = 0;
            } else {
                $this->urlsRatio->ownDomainWpIncludesRatio = $this->urlsRatio->ownDomainWpIncludesCnt / $this->urlsRatio->ownDomainUrlsCnt;
            }

            //judgement
            $score = 0;
            $msgEn = '';
            $msgJa = '';
            $tempArr = $this->directories;
            foreach ($this->directories as $oneDirectory) {
                if ($oneDirectory->isExist_wp_login) {
                    $score += 100;
                    $msgEn .= ' wp-login.php exist in ' . $oneDirectory->url . 'wp-login.php' . "<br />";
                    $msgJa .= ' wp-login.php が存在します。 ' . $oneDirectory->url . 'wp-login.php' . "<br />";
                }
                if ($oneDirectory->isExist_wordpress_org_in_readme_html) {
                    $score += 100;
                    $msgEn .= ' readme.html exist and link to wordpres.org in ' . $oneDirectory->url . 'readme.html' . "<br />";
                    $msgJa .= ' readme.html が存在し、そこから wordpres.org へのリンクが存在します。 ' . $oneDirectory->url . 'readme.html' . "<br />";
                }
                if ($oneDirectory->isExist_wordpress_org_in_sitemap) {
                    $score += 100;
                    $msgEn .= ' sitemap.xml and link to wordpres.org exist in ' . $oneDirectory->url . 'sitemap.xml' . "<br />";
                    $msgJa .= ' sitemap.xml が存在し、そこから wordpres.org へのシンクが存在します。 ' . $oneDirectory->url . 'sitemap.xml' . "<br />";
                }
            }
            $score += $this->urlsRatio->ownDomainWpContentRatio * 400;
            $msgEn .= ' urls whitch includes wp-content in ' . $this->url . ' ratio is ' . ((integer) ($this->urlsRatio->ownDomainWpContentRatio * 100)) . '%' . "<br />";
            $msgJa .= ' 指示されたURL ' . $this->url . ' の中のリンクに、 wp-content を含む比率は ' . ((integer) ($this->urlsRatio->ownDomainWpContentRatio * 100)) . '% でした。' . "<br />";

            $score += $this->urlsRatio->ownDomainWpIncludesRatio * 400;
            $msgEn .= ' urls whitch includes wp-includes in ' . $this->url . ' ratio is ' . ((integer) ($this->urlsRatio->ownDomainWpIncludesRatio * 100)) . '%' . "<br />";
            $msgJa .= ' 指示されたURL ' . $this->url . ' の中のリンクに、 wp-includes を含む比率は ' . ((integer) ($this->urlsRatio->ownDomainWpIncludesRatio * 100)) . '% でした。' . "<br />";

            if ($this->isExist->PoweredbyWordPress) {
                $score += 100;
                $msgEn .= ' "Powerd by WordPress" exist in ' . $this->url . "<br />";
                $msgJa .= ' 指示されたURL ' . $this->url . ' の中に、 "Powerd by WordPress" がありました。' . "<br />";
            }
            if (100 <= $score) {
                $this->result = true;
            } else {
                $this->result = false;
            }
            $this->score = $score;
            $this->msgResultEn = $msgEn;
            $this->msgResultJa = $msgJa;
        }
        $this->log($this->result . "\t" . $this->url . "\t" . $this->directories->isExist_sitemap_xml);
    }

    function retDirectories($urlForDirectories) {
        //input: http://accountingse.net/2017/03/1045/
        //output: (array)
        //http://accountingse.net/
        //http://accountingse.net/2017/
        //http://accountingse.net/2017/03/
        //http://accountingse.net/2017/03/1045/
        $urlHead;
        if (strpos($urlForDirectories, 'https://') === false) {
            $urlHead = 'http://';
        } else {
            $urlHead = 'https://';
        }
        $S = str_replace($urlHead, '', $urlForDirectories);
        $tempArr = explode('/', $S);
        $retDirectoriesArr = array();
        $connectedUrl = $urlHead;
        foreach ($tempArr as $oneDirectory) {
            $connectedUrl .= $oneDirectory . '/';
            $retDirectoriesArr[] = $connectedUrl;
        }
        return($retDirectoriesArr);
    }

    function checkReadmeHtml($html, $strForPregMatchUrls) {
        // if exists wordpress.org in html, return true.
        preg_match_all($strForPregMatchUrls, $html, $urlsInReadmeHtml);
        $Flg = false;
        foreach ($urlsInReadmeHtml[0] as $oneUrl) {
            if (strpos($oneUrl, 'wordpress.org') === false) {
                //do nothing
            } else {
                $Flg = true;
            }
        }
        return($Flg);
    }

    function log($S) {
        $logFilePath = __DIR__;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $logFilePath .= '\log.txt';
        } else {
            $logFilePath .= '/log.txt';
        }
        file_put_contents($logFilePath, date('Y-m-d H:i:s') . "\t" . $_SERVER['REMOTE_ADDR'] . "\t" . $S . "\n", FILE_APPEND);
    }

}

class isExistInHtml {

    public $WordPress;
    public $byWordPress;
    public $PoweredbyWordPress;

}

class urlInHtml {

    public $url;
    public $scheme;
    public $domain;
    public $isOwnDomain;
    public $isExist_wp;
    public $isExist_wpincludes;
    public $isExist_wpcontent;

}

class directoryCheck {

    public $url;
    public $isExist_sitemap_xml;
    public $isExist_sitemap_xml_gz;
    public $isExist_wordpress_org_in_sitemap;
    public $isExist_wp_login;
    public $isExist_readme_html;
    public $isExist_wordpress_org_in_readme_html;

}

class parseUrl {

    public $domain;
    public $scheme;

}

class urlRatio {

    public $allUrlsCnt;
    public $allWpIncludesCnt;
    public $allWpContentCnt;
    public $allWpIncludesRatio;
    public $allWpContentRatio;
    public $ownDomainUrlsCnt;
    public $ownDomainWpIncludesCnt;
    public $ownDomainWpContentCnt;
    public $ownDomainWpIncludesRatio;
    public $ownDomainWpContentRatio;

}
