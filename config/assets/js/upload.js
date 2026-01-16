
document.getElementById("groomForm").addEventListener("submit", function(e) {
    e.preventDefault();
    var form = e.target;
    var formData = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open("POST", form.action, true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var percent = (e.loaded / e.total) * 100;
            document.getElementById("progressBar").style.width = percent + "%";
        }
    };

    xhr.onload = function() {
        if (xhr.status === 200) {
            alert("تم رفع العريس بنجاح");
            window.location.reload();
        } else {
            alert("فشل في الرفع");
        }
    };

    xhr.send(formData);
});
