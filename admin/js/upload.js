
function uploadImages() {
    const files = document.getElementById('images').files;
    const formData = new FormData();

    for (let i = 0; i < files.length; i++) {
        formData.append("images[]", files[i]);
    }

    fetch('upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        document.getElementById('status').innerText = result;
    })
    .catch(error => {
        document.getElementById('status').innerText = "فشل الرفع";
    });
}
