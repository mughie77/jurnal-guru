<?php
class DapodikHelper {
    private $url;
    private $token;

    public function __construct($conn) {
        $res = mysqli_query($conn, "SELECT * FROM pengaturan WHERE nama_setting IN ('dapodik_url', 'dapodik_token')");
        $sets = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $sets[$r['nama_setting']] = $r['nilai_setting'];
        }
        $this->url = rtrim($sets['dapodik_url'] ?? '', '/');
        $this->token = $sets['dapodik_token'] ?? '';
    }

    public function fetch($endpoint) {
        if (empty($this->url) || empty($this->token)) {
            throw new Exception("Dapodik URL or Token not configured.");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->token,
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("CURL Error: " . $error);
        }

        if ($http_code !== 200) {
            throw new Exception("Dapodik API returned HTTP " . $http_code);
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['rows'])) {
            throw new Exception("Invalid response format from Dapodik.");
        }

        return $data['rows'];
    }

    public function getSiswa() {
        return $this->fetch("/getPesertaDidik");
    }

    public function getGuru() {
        return $this->fetch("/getGtk");
    }

    public function getRombel() {
        return $this->fetch("/getRombonganBelajar");
    }
}
