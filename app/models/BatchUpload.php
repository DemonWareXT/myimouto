<?php
class BatchUpload extends Rails\ActiveRecord\Base
{
    public $data;
    
    /**
     * Flag to know the upload is 100% finished.
     */
    public $finished = false;

    public function run()
    {
        Rails::systemExit()->register(function() {
            if (!$this->finished) {
                $this->active = false;
                $this->data->success = false;
                $this->data->error = "Couldn't finish successfuly";
                $this->save();
            }
        });
        
        # Ugly: set the current user ID to the one set in the batch, so history entries
        # will be created as that user.
        // $old_thread_user = Thread::current["danbooru-user"];
        // $old_thread_user_id = Thread::current["danbooru-user_id"];
        // $old_ip_addr = Thread::current["danbooru-ip_addr"];
        // Thread::current["danbooru-user"] = User::find_by_id(self.user_id)
        // Thread::current["danbooru-user_id"] = $this->user_id
        // Thread::current["danbooru-ip_addr"] = $this->ip

        $this->active = true;
        $this->save();

        $this->post = Post::create(['source' => $this->url, 'tags' => $this->tags, 'updater_user_id' => $this->user_id, 'updater_ip_addr' => $this->ip, 'user_id' => $this->user_id, 'ip_addr' => $this->ip, 'status' => "active", 'is_upload' => false]);

        if ($this->post->errors()->blank()) {
            if (CONFIG()->dupe_check_on_upload && $this->post->image() && !$this->post->parent_id) {
                $options = [ 'services' => SimilarImages::get_services("local"), 'type' => 'post', 'source' => $this->post ];

                $res = SimilarImages::similar_images($options);
                if (!empty($res['posts'])) {
                    $this->post->tags = $this->post->tags() . " possible_duplicate";
                    $this->post->save();
                }
            }
            $this->data->success = true;
            $this->data->post_id = $this->post->id;
        } elseif ($this->post->errors()->on('md5')) {
            // $p = $this->post->errors();
            $p = Post::where(['md5' => $this->post->md5])->first();
            
            $this->data->success = false;
            $this->data->error = "Post already exists";
            $this->data->post_id = $p->id;
       } else {
            // p $this->post.errors
            $this->data->success = false;
            $this->data->error = $this->post->errors()->fullMessages(", ");
        }

        if ($this->data->success) {
            $this->status = 'finished';
        } else {
            $this->status = 'error';
        }

        $this->active = false;
        
        $this->save();

        $this->finished = true;
        // Thread::current["danbooru-user"] = old_thread_user
        // Thread::current["danbooru-user_id"] = old_thread_user_id
        // Thread::current["danbooru-ip_addr"] = old_ip_addr
    }
    
    protected function associations()
    {
        return [
            'belongs_to' => [
                'user'
            ]
        ];
    }

    protected function init()
    {
        $this->data = json_decode($this->data_as_json) ?: new stdClass();
    }
    
    protected function encode_data()
    {
        $this->data_as_json = json_encode($this->data);
    }
    
    // protected function data_setter($hoge)
    // {
        // $this->data_as_json = json_encode($hoge);
    // }

    protected function callbacks()
    {
        return [
            'before_save' => [
                'encode_data'
            ]
        ];
    }
}