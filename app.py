import requests

url = "http://localhost/kacafix_api/Api/auth.php"

payload = ""
headers = {
    "cookie": "token=bar",
    "User-Agent": "insomnia/9.3.3",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJUSEVfSVNTVUVSIiwiYXVkIjoiVEhFX0FVRElFTkNFIiwiaWF0IjoxNzI1NjU3MjYxLCJuYmYiOjE3MjU2NTcyNzEsImV4cCI6MTcyNTY1NzMyMSwiZGF0YSI6eyJpZCI6IjE0IiwidXNlcm5hbWUiOiJqb2huIn19.CxcnRySDPleEu7p6JA10I0Dde72jaymWiPi7nSp-JyI"
}

response = requests.request("POST", url, data=payload, headers=headers)

print(response.text)