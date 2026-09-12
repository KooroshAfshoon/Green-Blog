


function likePost(id, btn) {
    let likedPosts = JSON.parse(localStorage.getItem('likedPosts') || "[]");
    const isLiked = likedPosts.includes(id);
    const action = isLiked ? 'unlike' : 'like';

    fetch(`like.php?id=${id}&action=${action}`)
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            btn.querySelector('.count').innerText = data.new_likes;
            
            if (isLiked) {
                likedPosts = likedPosts.filter(postId => postId !== id);
                btn.classList.remove('is-liked');
            } else {
                likedPosts.push(id);
                btn.classList.add('is-liked');
            }
            localStorage.setItem('likedPosts', JSON.stringify(likedPosts));
        }
    });
}