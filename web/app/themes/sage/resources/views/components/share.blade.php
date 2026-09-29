@props([
    'title' => '',
    'link' => '',
    'facebook' => true,
    'twitter' => true,
    'linkedin' => true,
    'whatsapp' => true,
])

<div class="flex items-start justify-start gap-3 flex-col md:flex-row md:items-center">
  <span class="text-base text-primary-500 font-body font-medium">{{ __('Share on', 'sage') }}</span>
  <div class="flex items-center justify-start gap-2 flex-wrap">

    @if($facebook)
      <a href="https://www.facebook.com/sharer/sharer.php?u={{ $link }}" target="_blank"
         aria-label="{{ __('Share on Facebook', 'sage') }}"
         class="w-8 min-w-8 max-w-8 h-8 aspect-square rounded-full flex items-center justify-center bg-[#1877F2] outline-offset-2 outline-[#1877F2] hover:outline-2 focus:!outline-2">
        <svg class="h-4 w-auto fill-white" viewBox="0 0 640 640">
          <path d="M240 363.3L240 576L356 576L356 363.3L442.5 363.3L460.5 265.5L356 265.5L356 230.9C356 179.2 376.3 159.4 428.7 159.4C445 159.4 458.1 159.8 465.7 160.6L465.7 71.9C451.4 68 416.4 64 396.2 64C289.3 64 240 114.5 240 223.4L240 265.5L174 265.5L174 363.3L240 363.3z"/>
        </svg>
      </a>
    @endif

    @if($twitter)
      <a href="https://x.com/intent/share?url={{ $link }}&text={{ $title }}" target="_blank"
         aria-label="{{ __('Share on Twitter X', 'sage') }}"
         class="w-8 min-w-8 max-w-8 h-8 aspect-square rounded-full flex items-center justify-center bg-black outline-offset-2 outline-black hover:outline-2 focus:!outline-2">
        <svg class="h-4 w-auto fill-white" viewBox="0 0 640 640">
          <path d="M453.2 112L523.8 112L369.6 288.2L551 528L409 528L297.7 382.6L170.5 528L99.8 528L264.7 339.5L90.8 112L236.4 112L336.9 244.9L453.2 112zM428.4 485.8L467.5 485.8L215.1 152L173.1 152L428.4 485.8z"/>
        </svg>
      </a>
    @endif

    @if($linkedin)
      <a href="https://www.linkedin.com/shareArticle?url={{ $link }}&title={{ $title }}" target="_blank"
         aria-label="{{ __('Share on LinkedIn', 'sage') }}"
         class="w-8 min-w-8 max-w-8 h-8 aspect-square rounded-full flex items-center justify-center bg-[#0077B5] outline-offset-2 outline-[#0077B5] hover:outline-2 focus:!outline-2">
        <svg class="h-4 w-auto fill-white" viewBox="0 0 640 640">
          <path d="M196.3 512L103.4 512L103.4 212.9L196.3 212.9L196.3 512zM149.8 172.1C120.1 172.1 96 147.5 96 117.8C96 103.5 101.7 89.9 111.8 79.8C121.9 69.7 135.6 64 149.8 64C164 64 177.7 69.7 187.8 79.8C197.9 89.9 203.6 103.6 203.6 117.8C203.6 147.5 179.5 172.1 149.8 172.1zM543.9 512L451.2 512L451.2 366.4C451.2 331.7 450.5 287.2 402.9 287.2C354.6 287.2 347.2 324.9 347.2 363.9L347.2 512L254.4 512L254.4 212.9L343.5 212.9L343.5 253.7L344.8 253.7C357.2 230.2 387.5 205.4 432.7 205.4C526.7 205.4 544 267.3 544 347.7L544 512L543.9 512z"/>
        </svg>
      </a>
    @endif

    @if($whatsapp)
      <a href="whatsapp://send?text={{ $link }}" target="_blank"
         aria-label="{{ __('Share on WhatsApp', 'sage') }}"
         class="w-8 min-w-8 max-w-8 h-8 aspect-square rounded-full flex items-center justify-center bg-[#25D366] outline-offset-2 outline-[#25D366] hover:outline-2 focus:!outline-2">
        <svg class="h-4 w-auto fill-white" viewBox="0 0 640 640">
          <path d="M476.9 161.1C435 119.1 379.2 96 319.9 96C197.5 96 97.9 195.6 97.9 318C97.9 357.1 108.1 395.3 127.5 429L96 544L213.7 513.1C246.1 530.8 282.6 540.1 319.8 540.1L319.9 540.1C442.2 540.1 544 440.5 544 318.1C544 258.8 518.8 203.1 476.9 161.1zM319.9 502.7C286.7 502.7 254.2 493.8 225.9 477L219.2 473L149.4 491.3L168 423.2L163.6 416.2C145.1 386.8 135.4 352.9 135.4 318C135.4 216.3 218.2 133.5 320 133.5C369.3 133.5 415.6 152.7 450.4 187.6C485.2 222.5 506.6 268.8 506.5 318.1C506.5 419.9 421.6 502.7 319.9 502.7zM421.1 364.5C415.6 361.7 388.3 348.3 383.2 346.5C378.1 344.6 374.4 343.7 370.7 349.3C367 354.9 356.4 367.3 353.1 371.1C349.9 374.8 346.6 375.3 341.1 372.5C308.5 356.2 287.1 343.4 265.6 306.5C259.9 296.7 271.3 297.4 281.9 276.2C283.7 272.5 282.8 269.3 281.4 266.5C280 263.7 268.9 236.4 264.3 225.3C259.8 214.5 255.2 216 251.8 215.8C248.6 215.6 244.9 215.6 241.2 215.6C237.5 215.6 231.5 217 226.4 222.5C221.3 228.1 207 241.5 207 268.8C207 296.1 226.9 322.5 229.6 326.2C232.4 329.9 268.7 385.9 324.4 410C359.6 425.2 373.4 426.5 391 423.9C401.7 422.3 423.8 410.5 428.4 397.5C433 384.5 433 373.4 431.6 371.1C430.3 368.6 426.6 367.2 421.1 364.5z"/>
        </svg>
      </a>
    @endif

  </div>
</div>
