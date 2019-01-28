<?php
// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


/**
 * Class ilExamAdminUserQuery
 */
abstract class ilExamAdminUserQuery
{
    /** @var ilDB */
    protected $db;


    /**
     * Get User data ba user id
     * @param string $login
     * @return array|null   single user data record
     */
    public function getSingleUserDataByLogin($login)
    {
        $data =  $this->queryUserData('login=' . $this->db->quote($login, 'text'));
        if (count($data))
        {
            return $data[0];
        }
        return null;
    }


    /**
     * Get User data ba user id
     * @param integer $id
     * @return array|null single user data record
     */
    public function getSingleUserDataById($id)
    {
        $data =  $this->queryUserData('usr_id=' . $this->db->quote($id, 'integer'));
        if (count($data))
        {
            return $data[0];
        }
        return null;
    }

    /**
     * Get the data of testaccounts for a login
     * @param string $login
     * @return array
     */
    public function getTestaccountData($login)
    {
        return $this->queryUserData($this->getTestaccountCond($login));
    }


    /**
     * Get the daty of accountd found by an input of matriculation numbers
     * @param string $list
     * @return array
     */
    public function getUserDataByMatriculationList($input)
    {
        return $this->queryUserData($this->getCondByMatriculationList($input));
    }

    /**
     * Get the data of users by a list of user IDs
     * @param int[] $ids
     * @return array
     */
    public function getUserDataByIds($ids = [])
    {
        return $this->queryUserData($this->db->in('usr_id', $ids, false, 'integer'));
    }

    /**
     * Extract the user ids from a list of user data
     * @param array $data
     * @return int[]
     */
    public function extractUserIds($data)
    {
        $ids = [];
        foreach ($data as $row)
        {
            $ids[] = $row['usr_id'];
        }
        return $ids;
    }


    /**
     * Query the user ids for a condition
     * @param string $condition
     * @return array
     */
    protected function queryUserIds($condition)
    {
        $query = "SELECT usr_id FROM usr_data WHERE (" . $condition . ")";

        $result = $this->db->query($query);
        $ids = [];
        while($row = $this->db->fetchAssoc($result))
        {
            $ids[] = $row['usr_id'];
        }
        return $ids;
    }


    /**
     * Query the user data for a condition
     * @param string $condition
     * @return array
     */
    protected function queryUserData($condition)
    {
        $query = "SELECT * FROM usr_data WHERE (" . $condition . ")"
            . " ORDER BY lastname, firstname, login";
        $result = $this->db->query($query);
        $data = [];
        while($row = $this->db->fetchAssoc($result))
        {
            $row['name'] = $row['lastname'] . ", " . $row['firstname'];
            if ($row['time_limit_unlimited']) {
                $row['time_limit_until'] = 0;
            }
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Get the search condition according to a pattern
     * @return string
     */
    protected function getSearchCond($pattern)
    {
        $pattern = trim($pattern);
        if (strpos($pattern, ',') > 0)
        {
            $flip_names = true;
        }
        $pattern = preg_replace('/,/', '', $pattern);
        $pattern = preg_replace('/ +/', ' ', $pattern);
        $parts = explode(' ', $pattern);

        if (count($parts) == 1)
        {
            if (is_numeric($parts[0]))
            {
                return 'matriculation = ' . $this->db->quote($parts[0]);
            }
            else
            {
                return 'login = ' . $this->db->quote($parts[0]) . ' OR lastname = ' .  $this->db->quote($parts[0]);
            }
        }
        elseif (count($parts) == 2 && !$flip_names)
        {
            return 'firstname = ' . $this->db->quote($parts[0]) . ' AND lastname = ' .  $this->db->quote($parts[1]);
        }
        elseif (count($parts) == 2 && $flip_names)
        {
            return 'lastname = ' . $this->db->quote($parts[0]) . ' AND firstname = ' .  $this->db->quote($parts[1]);
        }
        else
        {
            return '';
        }
    }


    /**
     * Get a search condition for a matriculation list
     * @param string $input
     * @return string
     */
    protected function getCondByMatriculationList($input)
    {
        $list = $this->getArrayFromListInput($input);
        return $this->db->in('matriculation', $list, false, 'text');
    }


    /**
     * Get an array from comma or newline separated input
     * @param string $input
     * @return array
     */
    protected function getArrayFromListInput($input)
    {
        $input = preg_replace('/\r/', ',', $input);   // carriage return to comma
        $input = preg_replace('/\n/', ',', $input);   // newline to comma
        $input = preg_replace('/;/', ',', $input);   // semicolon to comma
        $array = explode(',', $input);

        $trimmed = [];
        foreach ($array as $element)
        {
            $t = trim($element);
            if (!empty($t))
            {
                $trimmed[] = $t;
            }
        }
        return $trimmed;
    }



    /**
     * Get the search condition for testaccounts
     * @param string|null $login
     * @return string
     */
    protected function getTestaccountCond($login = null)
    {
        if ($login) {
            return "(login =" .$this->db->quote($login .'.test1', 'text')
                ." OR login =" .$this->db->quote($login .'.test2', 'text') .")";
        }

        return "(login LIKE '%.test1' OR login LIKE '%.test2')";
    }



}
