<?php
/**
* Atarashii MAL API
*
* @author    Ratan Dhawtal <ratandhawtal@hotmail.com>
* @author    Michael Johnson <youngmug@animeneko.net>
* @copyright 2014 Ratan Dhawtal and Michael Johnson
* @license   http://www.apache.org/licenses/LICENSE-2.0 Apache Public License 2.0
*/

namespace Atarashii\APIBundle\Parser;

use Symfony\Component\DomCrawler\Crawler;
use Atarashii\APIBundle\Model\forum;
use Atarashii\APIBundle\Model\profile;

class ForumParser
{
    public static function parseBoard($contents)
    {
        $crawler = new Crawler();
        $crawler->addHTMLContent($contents, 'UTF-8');

        $boarditems = $crawler->filter('tr');
        foreach ($boarditems as $item) {
            //Trick to force json array and not json objects.
            $set = self::parseBoards($item);
            if ($set != null) {
                $resultset[] = $set;
            }
        }

        return $resultset;
    }

    private static function parseBoards($item)
    {
        $crawler = new Crawler($item);
        if ($crawler->filter('td')->count() >= 2) {
            $board = new Forum();

            # name.
            # Example:
            # <strong>Updates &amp; Announcements</strong>
            $board->setName($crawler->filter('strong')->text());

            # description.
            # Example:
            # <br> Updates, changes, and additions to MAL.</br>
            $board->setDescription(str_replace($board->getName()."\n          \n\t\t  ", '', $crawler->filter('td[class="forum_boardrow1"]')->text()));

            if ($crawler->filter('td[class=forum_boardrow1] a')->count() == 1) {
                # id.
                # Example:
                # <strong>Anime DB</strong>
                $board->setId(str_replace('?board=', '', $crawler->filter('td[class="forum_boardrow1"] a')->attr('href')));
            } else {
                $childerenitems = $crawler->filter('td[class="forum_boardrow1"] a');
                foreach ($childerenitems as $children) {
                    $crawler = new Crawler($children);
                    $child = new Forum();

                    # name.
                    # Example:
                    # <strong>Updates &amp; Announcements</strong>
                    $child->setName($crawler->filter('a strong')->text());

                    # id.
                    # Example:
                    # <a href="?subboard=5">...</a>
                    $child->setId(str_replace('?subboard=', '', $crawler->attr('href')));

                    $board->setChildren($child);
                }
            }

            return $board;
        } else {
            return null;
        }
    }

    public static function parseTopics($contents)
    {
        $crawler = new Crawler();
        $crawler->addHTMLContent($contents, 'UTF-8');

        $topicsitems = $crawler->filter('tr');
        foreach ($topicsitems as $item) {
            //Trick to force json array and not json objects.
            $set = self::parseTopicsDetails($item);
            if ($set != null) {
                $resultset[] = $set;
            }
        }

        return $resultset;
    }

    private static function parseTopicsDetails($item)
    {
        $crawler = new Crawler($item);
        if ($crawler->filter('td')->count() >= 4) {
            $topics = new Forum();

            # id.
            # Example:
            # <span id="wt439011">...</span>
            $topics->setId(str_replace('/forum/?topicid=', '', $crawler->filter('td[class="forum_boardrow1"] a')->attr('href')));//->filter('span')->attr('id')));

            # name.
            # Example:
            # <a href="/forum/?topicid=439011">BBCode Fixes</a>
            $topics->setName($crawler->filter('td[class="forum_boardrow1"] a')->text());

            # username.
            # Example:
            # <a href="/profile/ratan12">ratan12</a>
            $topics->setUsername(str_replace('?board=', '', $crawler->filter('span[class="forum_postusername"] a')->text()));

            # replies.
            # Example:
            # <td align="center" width="75" class="forum_boardrow2" style="border-width: 0px 1px 1px 0px;">159</td>
            $topics->setReplies(str_replace('?board=', '', $crawler->filter('td[class="forum_boardrow2"]')->eq(1)->text()));

            //note: eq(1) is the second node and !first.
            $username = $crawler->filter('td[class="forum_boardrow1"]')->eq(1)->filter('a')->text();
            $time = explode("\n", $crawler->filter('td[class="forum_boardrow1"]')->eq(1)->text());
            $topics->setReply(array('username' => $username , 'time' => $time[1]));

            return $topics;
        } else {
            return null;
        }
    }

    public static function parseTopic($contents)
    {
        $crawler = new Crawler();
        $crawler->addHTMLContent($contents, 'UTF-8');

        $topicitems = $crawler->filter('div[class="forum_border_around"]');
        foreach ($topicitems as $item) {
            $resultset[] = self::parseTopicDetails($item);
        }

        return $resultset;
    }

    private static function parseTopicDetails($item)
    {
        $crawler = new Crawler($item);
            $topic = new Forum();
            $topic->user = new Profile();

            # image url.
            # Example:
            # <img src="http://cdn.myanimelist.net/images/useravatars/1901304.jpg" vspace="2" border="0">
            //Note: Some MAL users do not have any avatars in the forum!
            try {
                $topic->user->setAvatarUrl($crawler->filter('img')->attr('src'));
            } catch (\InvalidArgumentException $e) {
                //do nothing
            }

            $details = explode("\n\t\t  ", $crawler->filter('td[class="forum_boardrow2"]')->text());
            $topic->setUsername($details[0]);
            $topic->user->details->setStatus($details[4]);
            $topic->user->details->setJoinDate(str_replace('Joined: ', '', $details[5]));
            $topic->user->details->setForumPosts(str_replace('Posts: ', '', $details[6]));

            # comment.
            # Example:
            # <div id="message25496275">...</div>
            $topic->setComment($crawler->filter('td[class="forum_boardrow1"] div')->html());
            return $topic;
    }

}
