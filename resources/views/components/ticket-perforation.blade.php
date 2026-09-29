@props(['orientation' => 'horizontal'])

@if ($orientation === 'horizontal')
    <div class="ticket-perforation--horizontal">
        <div class="ticket-perforation__notch ticket-perforation__notch--left"></div>
        <div class="ticket-perforation__dash"></div>
        <div class="ticket-perforation__notch ticket-perforation__notch--right"></div>
    </div>
@else
    <div class="ticket-perforation--vertical">
        <div class="ticket-perforation__notch ticket-perforation__notch--top"></div>
        <div class="ticket-perforation__dash"></div>
        <div class="ticket-perforation__notch ticket-perforation__notch--bottom"></div>
    </div>
@endif
