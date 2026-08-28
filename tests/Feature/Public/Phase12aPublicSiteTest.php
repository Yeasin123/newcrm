<?php

use App\Models\Blog;
use App\Models\BuyerSeller;
use App\Models\ContactUs;
use App\Models\Partner;
use Illuminate\Support\Carbon;

test('every guest marketing page renders', function () {
    foreach ([
        '/', '/about', '/privacy/policy', '/terms-and-conditions', '/sample-policy',
        '/success', '/blog', '/partner', '/buyer-seller', '/plans',
    ] as $path) {
        $this->get($path)->assertOk();
    }

    expect(route('homepage'))->toBe(url('/'));
});

test('the home page shows the hero and the customer-login link', function () {
    $this->get('/')
        ->assertSee('AHC CRM')
        ->assertSee('Customer login')
        ->assertSee(route('shop.planes'));
});

test('the blog only lists and shows published posts', function () {
    $live = Blog::create(['title' => 'Live Post', 'slug' => 'live-post', 'content' => '<p>hi</p>', 'is_published' => true, 'published_at' => Carbon::now()->subDay()]);
    $draft = Blog::create(['title' => 'Draft Post', 'slug' => 'draft-post', 'content' => '<p>wip</p>', 'is_published' => false]);

    $this->get('/blog')->assertOk()->assertSee('Live Post')->assertDontSee('Draft Post');
    $this->get(route('blogs.show', $live))->assertOk()->assertSee('Live Post');
    $this->get(route('blogs.show', $draft))->assertNotFound();
});

test('the about contact form stores a row and validates the email', function () {
    $this->post('/about/contact', [
        'first_name' => 'Pat', 'email' => 'pat@example.com', 'description' => 'Interested in a plan.',
    ])->assertRedirect();

    expect(ContactUs::where('email', 'pat@example.com')->exists())->toBeTrue();

    $this->post('/about/contact', ['first_name' => 'Pat', 'email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
    expect(ContactUs::count())->toBe(1);
});

test('the partner and buyer-seller forms each store to their own table', function () {
    $this->post('/partner', ['first_name' => 'Rita', 'email' => 'rita@realty.com', 'company_name' => 'Rita Realty'])
        ->assertRedirect();
    $this->post('/buyer-seller', ['first_name' => 'Bob', 'email' => 'bob@example.com'])
        ->assertRedirect();

    expect(Partner::where('email', 'rita@realty.com')->value('company_name'))->toBe('Rita Realty')
        ->and(BuyerSeller::where('email', 'bob@example.com')->exists())->toBeTrue();
});
