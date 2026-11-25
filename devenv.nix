{ pkgs, ... }:

{
  packages = with pkgs; [
    chromium
    git
    gnutar
    just
    nodejs_24
    php83
  ];

  env.PLAN_SCREENSHOT_CHROMIUM = "${pkgs.chromium}/bin/chromium";
}
